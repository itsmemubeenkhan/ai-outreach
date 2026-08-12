<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;

class BrandSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Brand::upsert([
            ['name' => 'The Brand Maker', 'slug' => 'the-brand-maker', 'is_active' => true],
            ['name' => 'Aspire Website Designs', 'slug' => 'aspire-website-designs', 'is_active' => true],
        ], ['slug'], ['name', 'is_active']);
    }
}
