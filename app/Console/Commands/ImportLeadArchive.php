<?php

namespace App\Console\Commands;

use App\Jobs\ProcessLeadImport;
use App\Models\LeadImport;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class ImportLeadArchive extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'outreach:import-archive {archive : Absolute ZIP path} {--user= : Owner email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Stream every CSV in a ZIP into queued lead imports';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $path = (string) $this->argument('archive');
        if (! is_file($path)) {
            $this->error('Archive does not exist.');

            return self::FAILURE;
        }
        $user = User::when($this->option('user'), fn ($query, $email) => $query->where('email', $email))->first();
        if (! $user) {
            $this->error('No import owner found. Pass --user=email@example.com.');

            return self::FAILURE;
        }
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            $this->error('Unable to open ZIP archive.');

            return self::FAILURE;
        }
        $queued = 0;
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entry = $zip->getNameIndex($index);
            if (! $entry || ! str_ends_with(strtolower($entry), '.csv')) {
                continue;
            }
            $stream = $zip->getStream($entry);
            if (! $stream) {
                continue;
            }
            $headerLine = fgets($stream);
            fclose($stream);
            $headers = str_getcsv((string) $headerLine);
            $mapping = $this->mapping($headers);
            if ($mapping === []) {
                $this->warn("Skipped unsupported CSV: {$entry}");

                continue;
            }
            $storedPath = 'imports/archive/'.sha1($entry).'.csv';
            if (! Storage::exists($storedPath)) {
                $stream = $zip->getStream($entry);
                Storage::writeStream($storedPath, $stream);
                fclose($stream);
            }
            $import = LeadImport::firstOrCreate(
                ['user_id' => $user->id, 'stored_path' => $storedPath],
                ['original_filename' => basename(str_replace('\\', '/', $entry)), 'column_mapping' => $mapping, 'status' => 'queued']
            );
            if ($import->wasRecentlyCreated || in_array($import->status, ['failed'], true)) {
                $import->update(['status' => 'queued', 'error_message' => null]);
                ProcessLeadImport::dispatch($import->id);
                $queued++;
            }
        }
        $zip->close();
        $this->info("Queued {$queued} CSV imports. Existing staged/completed files were not duplicated.");

        return self::SUCCESS;
    }

    private function mapping(array $headers): array
    {
        $normalized = array_map(fn ($header) => strtolower(trim((string) $header, " \t\n\r\0\x0B\"\xEF\xBB\xBF")), $headers);
        $googleBusiness = in_array('rating', $normalized, true) && in_array('reviews', $normalized, true);
        $aliases = [
            'business_name' => $googleBusiness ? ['name', 'business name', 'company'] : ['business name', 'company', 'organization'],
            'number_of_employees' => ['number of employees', 'employee count'],
            'contact_person' => $googleBusiness ? ['contact person'] : ['contact person', 'full name', 'name'],
            'first_name' => ['first name'], 'last_name' => ['last name'],
            'corporate_email' => ['corporate email', 'company email'],
            'email' => ['generic email', 'email', 'work email #1', 'direct email #1'],
            'website' => ['website', 'company website'],
            'phone' => ['phone', 'company phone', 'phone #1'], 'phone_type' => ['phone type'],
            'street_address' => ['street address', 'address'], 'zip_code' => ['zip code', 'zip', 'postal code'],
            'state' => ['state'], 'city' => ['city'], 'country' => ['country'],
            'category' => ['category', 'industry', 'query'], 'source' => ['source', 'linked url', 'url'],
        ];
        $mapping = [];
        foreach ($aliases as $field => $names) {
            foreach ($names as $name) {
                $position = array_search($name, $normalized, true);
                if ($position !== false) {
                    $mapping[$position] = $field;
                    break;
                }
            }
        }

        return $mapping;
    }
}
