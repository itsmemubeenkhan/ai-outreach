<?php

namespace App\Jobs;

use App\Models\ImportRejection;
use App\Models\Lead;
use App\Models\LeadImport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessLeadImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 1200;

    public const FIELDS = ['business_name', 'number_of_employees', 'contact_person', 'first_name', 'last_name', 'corporate_email', 'email', 'website', 'phone', 'phone_type', 'street_address', 'zip_code', 'state', 'city', 'country', 'category', 'source'];

    /**
     * Create a new job instance.
     */
    public function __construct(public int $importId) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $import = LeadImport::findOrFail($this->importId);
        $import->update(['status' => 'processing', 'started_at' => now(), 'error_message' => null]);
        $file = new \SplFileObject(Storage::path($import->stored_path));
        $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY | \SplFileObject::DROP_NEW_LINE);
        $headers = array_map('trim', $file->fgetcsv() ?: []);
        $mapping = $import->column_mapping;
        $defaults = is_array($mapping['_defaults'] ?? null) ? $mapping['_defaults'] : [];
        unset($mapping['_defaults']);
        $buffer = [];
        $processed = 0;
        $imported = 0;
        $duplicates = 0;
        $rejected = 0;
        while (! $file->eof()) {
            $row = $file->fgetcsv();
            if (! $row || $row === [null]) {
                continue;
            } $processed++;
            $data = [];
            foreach ($mapping as $index => $field) {
                if (in_array($field, self::FIELDS, true)) {
                    $data[$field] = isset($row[(int) $index]) ? trim((string) $row[(int) $index]) : null;
                }
            }
            $data = array_map(fn ($v) => $v === '' ? null : $v, $data);
            foreach (array_intersect_key($defaults, array_flip(self::FIELDS)) as $field => $value) {
                if (blank($data[$field] ?? null) && filled($value)) $data[$field] = trim((string) $value);
            }
            if (! empty($data['email'])) {
                $data['email'] = strtolower($data['email']);
            }
            if (! empty($data['email']) && ! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                ImportRejection::create(['lead_import_id' => $import->id, 'row_number' => $processed + 1, 'reason' => 'Invalid email', 'row_data' => $data]);
                $rejected++;

                continue;
            }
            if (! empty($data['phone'])) {
                $digits = preg_replace('/\D+/', '', $data['phone']);
                if (strlen($digits) === 10) {
                    $data['phone'] = '+1'.$digits;
                } elseif (strlen($digits) === 11 && str_starts_with($digits, '1')) {
                    $data['phone'] = '+'.$digits;
                }
            }
            $data += ['email_status' => 'unknown', 'lead_status' => 'new', 'lead_score' => 0];
            $data['created_at'] = $data['updated_at'] = now();
            $buffer[] = $data;
            if (count($buffer) >= 500) {
                $inserted = Lead::insertOrIgnore($buffer);
                $imported += $inserted;
                $duplicates += count($buffer) - $inserted;
                $buffer = [];
                $import->update(['processed_rows' => $processed, 'imported_rows' => $imported, 'duplicate_rows' => $duplicates, 'rejected_rows' => $rejected]);
            }
        }
        if ($buffer) {
            $inserted = Lead::insertOrIgnore($buffer);
            $imported += $inserted;
            $duplicates += count($buffer) - $inserted;
        }
        $import->update(['status' => 'completed', 'total_rows' => $processed, 'processed_rows' => $processed, 'imported_rows' => $imported, 'duplicate_rows' => $duplicates, 'rejected_rows' => $rejected, 'completed_at' => now()]);
    }

    public function failed(Throwable $e): void
    {
        LeadImport::whereKey($this->importId)->update(['status' => 'failed', 'error_message' => $e->getMessage(), 'completed_at' => now()]);
    }
}
