<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Workspace;
use App\Models\Project;
use App\Models\Industry;
use App\Models\RootKeyword;
use App\Models\SearchVariation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Seed User
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        // 2. Seed Workspace
        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => 'My Workspace',
            'description' => 'Default system workspace',
        ]);

        // Link User to Workspace
        $workspace->users()->attach($user->id, ['role' => 'owner']);

        // 3. Seed active Project
        Project::create([
            'workspace_id' => $workspace->id,
            'name' => 'General Outreach',
            'description' => 'Default outreach scrapes project',
            'status' => 'active',
        ]);

        // 4. Seed Industries
        $industriesList = [
            'Healthcare', 'Legal', 'Home Services', 'Beauty', 'Automotive',
            'Fitness', 'Finance', 'Education', 'Hospitality', 'Restaurant',
            'Real Estate', 'Retail', 'Manufacturing'
        ];

        $industries = [];
        foreach ($industriesList as $index => $name) {
            $industries[$name] = Industry::create([
                'name' => $name,
                'priority' => ($name === 'Healthcare' || $name === 'Home Services') ? 10 : 0
            ]);
        }

        // 5. Seed Root Keywords
        $keywordsList = [
            ['Healthcare', 'Dentist', 'Primary health dental care services'],
            ['Healthcare', 'Veterinarian', 'Pet clinics and animal care'],
            ['Home Services', 'Plumber', 'Pipe fixing and water installation services'],
            ['Home Services', 'HVAC', 'Heating, ventilation, and air conditioning'],
            ['Home Services', 'Roofing', 'Roof repair and tile installations'],
            ['Home Services', 'Electrician', 'Residential and industrial electrical services'],
            ['Legal', 'Lawyer', 'Attorneys and legal consultancy services'],
            ['Finance', 'CPA', 'Certified Public Accountants and tax services'],
            ['Beauty', 'Med Spa', 'Medical aesthetic treatments and skin care spa'],
            ['Restaurant', 'Restaurant', 'Dining venues, food outlets, and local cafes'],
            ['Automotive', 'Car Dealer', 'New and used vehicle dealerships'],
        ];

        $variations = [
            'Dentist' => ['Dental Clinic', 'Cosmetic Dentist', 'Emergency Dentist', 'Family Dentist', 'Dental Office'],
            'Plumber' => ['Emergency Plumber', 'Local Plumbing Services', 'Drain Cleaning Specialist', 'Residential Plumber'],
            'HVAC' => ['AC Repair Service', 'Heating Installation', 'HVAC Repair near me', 'Commercial Air Conditioning'],
            'Roofing' => ['Roof Repair Contractor', 'Local Roofers', 'Emergency Roof Leak Repair', 'Commercial Roofing'],
            'Electrician' => ['Emergency Electrician', 'Local Electrical Services', 'Residential Electrician', 'Electrical Contractor'],
            'Lawyer' => ['Personal Injury Attorney', 'Family Lawyer', 'Criminal Defense Lawyer', 'Business Law Firm'],
            'CPA' => ['Tax Accountant', 'Business Tax Services', 'CPA Firm near me', 'Bookkeeping Services'],
            'Med Spa' => ['Botox Clinic', 'Laser Hair Removal Spa', 'Medical Aesthetician', 'Skin Treatment Center'],
            'Restaurant' => ['Best Local Restaurants', 'Fine Dining Cafe', 'Family Restaurant', 'Food Delivery Place'],
            'Car Dealer' => ['Used Car Dealership', 'New Car Sales', 'Auto Broker', 'Car Financing Dealer'],
            'Veterinarian' => ['Animal Hospital', 'Emergency Vet Clinic', 'Local Veterinarians', 'Pet Doctor'],
        ];

        foreach ($keywordsList as $kw) {
            $industryName = $kw[0];
            $keywordVal = $kw[1];
            $desc = $kw[2];

            $root = RootKeyword::create([
                'industry_id' => $industries[$industryName]->id,
                'keyword' => $keywordVal,
                'slug' => Str::slug($keywordVal),
                'description' => $desc,
                'priority' => 0,
                'is_system' => true,
                'is_active' => true,
            ]);

            // Seed variations
            if (isset($variations[$keywordVal])) {
                foreach ($variations[$keywordVal] as $var) {
                    SearchVariation::create([
                        'root_keyword_id' => $root->id,
                        'keyword' => $var,
                        'source' => 'AI',
                        'is_active' => true,
                    ]);
                }
            }
        }
    }
}
