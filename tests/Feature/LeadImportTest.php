<?php

namespace Tests\Feature;

use App\Jobs\ProcessLeadImport;
use App\Models\LeadImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LeadImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_csv_rows_without_email_are_imported_but_invalid_emails_are_rejected(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('imports/leads.csv', "name,email,phone\nNo Email,,+1 737-555-0100\nBad Email,not-an-email,+1 737-555-0101\nValid Lead,VALID@EXAMPLE.COM,+1 737-555-0102\n");
        $import = LeadImport::create([
            'user_id' => User::factory()->create()->id,
            'original_filename' => 'leads.csv',
            'stored_path' => 'imports/leads.csv',
            'column_mapping' => [0 => 'business_name', 1 => 'email', 2 => 'phone'],
            'status' => 'queued',
        ]);

        (new ProcessLeadImport($import->id))->handle();

        $this->assertDatabaseHas('leads', ['business_name' => 'No Email', 'email' => null]);
        $this->assertDatabaseHas('leads', ['business_name' => 'Valid Lead', 'email' => 'valid@example.com']);
        $this->assertDatabaseMissing('leads', ['business_name' => 'Bad Email']);
        $this->assertSame(2, $import->refresh()->imported_rows);
        $this->assertSame(1, $import->rejected_rows);
    }

    public function test_import_can_apply_a_default_category(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('imports/dealers.csv', "business,phone\nDealer One,+1 737-555-0100\n");
        $import = LeadImport::create([
            'user_id' => User::factory()->create()->id,
            'original_filename' => 'dealers.csv',
            'stored_path' => 'imports/dealers.csv',
            'column_mapping' => [0 => 'business_name', 1 => 'phone', '_defaults' => ['category' => 'Car Dealership']],
            'status' => 'queued',
        ]);

        (new ProcessLeadImport($import->id))->handle();

        $this->assertDatabaseHas('leads', ['business_name' => 'Dealer One', 'category' => 'Car Dealership']);
    }
}
