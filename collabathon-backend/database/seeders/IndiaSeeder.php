<?php

namespace Database\Seeders;

use App\Models\BrokerProfile;
use App\Models\Developer;
use App\Models\Property;
use App\Models\PropertyDetail;
use App\Models\PropertyMedia;
use App\Models\PropertyUnitType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * India-market demo data: developers, projects and brokers across Hyderabad,
 * Bengaluru, Delhi and Mumbai, with downloaded logo/cover images so the admin
 * panel never shows a broken-image placeholder.
 *
 * Kept separate from DatabaseSeeder (which seeds unrelated UAE fixtures) so it
 * can be run on its own against a real database:
 *   php artisan db:seed --class=Database\\Seeders\\IndiaSeeder --force
 *
 * Idempotent — every write is updateOrCreate, so re-running never duplicates.
 * Every seeded account uses the password "password".
 */
class IndiaSeeder extends Seeder
{
    public function run(): void
    {
        $developers = $this->seedDevelopers();
        $this->seedProperties($developers);
        $this->seedBrokers();
    }

    /** @return array<string,Developer> keyed by company name */
    private function seedDevelopers(): array
    {
        $rows = [
            // company, contact, mobile, email, city, state, payout%
            ['Deccan Skyline Developers', 'Srinivas Rao', '+91 90000 11122', 'srinivas@deccanskyline.in', 'Hyderabad', 'Telangana', 2.5],
            ['Silicon Vista Builders', 'Kavya Shetty', '+91 90000 22233', 'kavya@siliconvista.in', 'Bengaluru', 'Karnataka', 3.0],
            ['Capital Crest Realty', 'Rohan Malhotra', '+91 90000 33344', 'rohan@capitalcrest.in', 'New Delhi', 'Delhi', 2.75],
            ['Marine Bay Estates', 'Ananya Kulkarni', '+91 90000 44455', 'ananya@marinebay.in', 'Mumbai', 'Maharashtra', 3.25],
        ];

        $out = [];

        foreach ($rows as [$company, $contact, $mobile, $email, $city, $state, $payout]) {
            $user = User::updateOrCreate(['email' => $email], [
                'name' => $contact,
                'password' => 'password',
                'role' => User::ROLE_DEVELOPER,
                'status' => User::STATUS_ACTIVE,
                'mobile' => $mobile,
                'email_verified_at' => now(),
            ]);

            $out[$company] = Developer::updateOrCreate(
                ['company_name' => $company],
                [
                    'user_id' => $user->id,
                    'contact_person' => $contact,
                    'mobile' => $mobile,
                    'email' => $email,
                    'city' => $city,
                    'state' => $state,
                    'rera_number' => 'RERA/' . Str::upper(Str::substr($state, 0, 3)) . '/DEV/' . rand(10000, 99999),
                    'cp_payout_percent' => $payout,
                    'verified' => true,
                    'status' => 'active',
                    'about' => "{$company} is a trusted real estate developer delivering premium residential "
                        . "and commercial projects across {$city}.",
                    'logo_path' => $this->downloadImage(
                        'https://ui-avatars.com/api/?name=' . urlencode($company)
                            . '&size=256&background=142441&color=fff&bold=true&format=png',
                        'developers/logos/' . Str::slug($company) . '.png'
                    ),
                ]
            );
        }

        return $out;
    }

    /** @param  array<string,Developer>  $developers */
    private function seedProperties(array $developers): void
    {
        $rows = [
            // developer, name, locality, city, state, type, status, min, max, units, interests, image keyword
            ['Deccan Skyline Developers', 'Deccan Horizon Towers', 'Gachibowli', 'Hyderabad', 'Telangana', 'Residential', 'Under Construction', 6_500_000, 12_000_000, 240, 34, 'apartment,skyline'],
            ['Deccan Skyline Developers', 'Skyview Business Park', 'HITEC City', 'Hyderabad', 'Telangana', 'Commercial', 'Ready to Move', 15_000_000, 30_000_000, 80, 21, 'office,building'],
            ['Silicon Vista Builders', 'Silicon Vista Residency', 'Whitefield', 'Bengaluru', 'Karnataka', 'Residential', 'Under Construction', 7_500_000, 14_000_000, 310, 46, 'apartment,modern'],
            ['Silicon Vista Builders', 'Tech Park Greens', 'Electronic City', 'Bengaluru', 'Karnataka', 'Mixed-use', 'New Launch', 6_000_000, 11_000_000, 190, 15, 'residential,tower'],
            ['Capital Crest Realty', 'Capital Crest Residences', 'Dwarka', 'New Delhi', 'Delhi', 'Residential', 'Ready to Move', 12_000_000, 22_000_000, 160, 38, 'apartment,exterior'],
            ['Capital Crest Realty', 'Crest Business Hub', 'Aerocity', 'New Delhi', 'Delhi', 'Commercial', 'Under Construction', 25_000_000, 45_000_000, 60, 19, 'office,tower'],
            ['Marine Bay Estates', 'Marine Bay Heights', 'Andheri West', 'Mumbai', 'Maharashtra', 'Residential', 'Under Construction', 18_000_000, 35_000_000, 220, 52, 'skyscraper,city'],
            ['Marine Bay Estates', 'Bay View Residences', 'Powai', 'Mumbai', 'Maharashtra', 'Residential', 'New Launch', 15_000_000, 28_000_000, 175, 27, 'apartment,lake'],
        ];

        foreach ($rows as $i => [$devName, $name, $locality, $city, $state, $type, $status, $min, $max, $units, $interests, $imgKeyword]) {
            $developer = $developers[$devName];
            $slug = Str::slug($name);

            $property = Property::updateOrCreate(
                ['slug' => $slug],
                [
                    'developer_id' => $developer->id,
                    'name' => $name,
                    'project_type' => $type,
                    'project_status' => $status,
                    'listing_status' => 'active',
                    'tagline' => 'Premium living, redefined in ' . $locality,
                    'description' => "{$name} by {$devName} offers thoughtfully designed spaces in {$locality}, "
                        . "{$city} — built for modern living with world-class amenities and excellent connectivity.",
                    'logo_path' => $this->downloadImage(
                        'https://ui-avatars.com/api/?name=' . urlencode($name)
                            . '&size=256&background=C9A227&color=142441&bold=true&format=png',
                        'properties/logos/' . $slug . '.png'
                    ),
                    'cover_image_path' => $this->downloadImage(
                        "https://loremflickr.com/1200/800/{$imgKeyword}?lock=" . (100 + $i),
                        'properties/covers/' . $slug . '.jpg'
                    ),
                    'rera_number' => 'RERA/' . Str::upper(Str::substr($state, 0, 3)) . '/PRJ/' . (20000 + $i),
                    'rera_registered_at' => now()->subMonths(18 - $i)->toDateString(),
                    'rera_valid_till' => now()->addYears(3)->toDateString(),
                    'state' => $state,
                    'city' => $city,
                    'locality' => $locality,
                    'full_address' => "{$name}, {$locality}, {$city}, {$state}",
                    'pincode' => (string) (500000 + $i * 111),
                    'zone' => ['East', 'West', 'North', 'South', 'Central'][$i % 5],
                    'price_min' => $min,
                    'price_max' => $max,
                    'price_per_sqft' => (int) round($min / 900),
                    'currency' => 'INR',
                    'total_units' => $units,
                    'towers' => 2 + ($i % 3),
                    'floors_per_tower' => 20 + $i * 2,
                    'flats_per_floor' => $type === 'Commercial' ? 6 : 8,
                    'land_parcel_acres' => 3.5 + $i,
                    'total_project_area_sqft' => 150000 + $i * 20000,
                    'open_space_percent' => 35 + ($i % 4) * 5,
                    'launch_date' => now()->subMonths(20 - $i * 2)->toDateString(),
                    'possession_date' => now()->addMonths(10 + $i * 3)->toDateString(),
                    'construction_progress' => $status === 'Ready to Move' ? 100 : (30 + $i * 6),
                    'vastu_compliant' => true,
                    'views_count' => 80 + $i * 23,
                    'interests_count' => $interests,
                ]
            );

            PropertyDetail::updateOrCreate(
                ['property_id' => $property->id],
                [
                    'connectivity_highlights' => [
                        'Metro station 900m',
                        'Airport ' . (18 + $i) . 'km',
                        'Ring road access 1.5km',
                    ],
                    'nearby_infrastructure' => [
                        'International school 1.2km',
                        'Multi-specialty hospital 2km',
                        'Shopping mall 2.5km',
                    ],
                    'construction_specifications' => 'RCC framed structure, vitrified tile flooring, branded '
                        . 'CP & sanitary fittings, premium modular kitchen.',
                    'amenities' => [
                        'Clubhouse', 'Swimming Pool', 'Gymnasium', 'Landscaped Gardens',
                        "Children's Play Area", '24x7 Security', 'Power Backup', 'Covered Parking',
                    ],
                    'amenities_count' => 8,
                    'parking_details' => 'Covered basement parking, 1–2 bays per unit',
                    'approving_authorities' => [$state . ' RERA', 'Municipal Corporation'],
                    'bank_approvals' => ['SBI', 'HDFC', 'ICICI Bank', 'Axis Bank'],
                    'payment_plan_options' => ['Construction-linked plan', '20:80 plan', 'Down payment plan'],
                    'booking_amount' => 200000,
                    'cp_commission_percent' => 2.0 + ($i % 3) * 0.5,
                    'maintenance_charges' => 'Rs. 3.5 / sq.ft. / month',
                    'sales_office_address' => "{$name} Experience Centre, {$locality}, {$city}",
                    'site_visit_timings' => 'Daily 10:00 AM - 7:00 PM',
                    'sales_contact_name' => 'Sales Desk',
                    'sales_contact_number' => '+91 1800 200 ' . (1000 + $i),
                ]
            );

            $property->unitTypes()->delete();
            $unitRows = $type === 'Commercial'
                ? [['Office Unit', 800], ['Retail Unit', 1200], ['Co-working Suite', 500]]
                : [['2BHK', 1050], ['3BHK', 1450], ['4BHK', 1950]];

            foreach ($unitRows as $j => [$label, $carpet]) {
                $band = (int) (($max - $min) / 4);

                PropertyUnitType::create([
                    'property_id' => $property->id,
                    'label' => $label,
                    'carpet_area_sqft' => $carpet,
                    'built_up_area_sqft' => (int) ($carpet * 1.15),
                    'super_built_up_area_sqft' => (int) ($carpet * 1.3),
                    'price_min' => $min + $j * $band,
                    'price_max' => $min + ($j + 1) * $band,
                    'units_count' => (int) ($units / 3),
                    'sort_order' => $j,
                ]);
            }

            $property->media()->where('kind', 'image')->delete();
            foreach (range(1, 5) as $k) {
                PropertyMedia::create([
                    'property_id' => $property->id,
                    'kind' => 'image',
                    'url' => "https://loremflickr.com/900/700/{$imgKeyword}?lock=" . ($i * 10 + $k),
                    'caption' => $name . ' - view ' . $k,
                    'sort_order' => $k,
                ]);
            }
        }
    }

    private function seedBrokers(): void
    {
        $pending = [
            ['Venkat Raidu', 'venkat@raiduproperties.in', '+91 91111 22233', 'Raidu Properties', 'Hyderabad', 'Telangana', ['Residential'], ['Gachibowli', 'Madhapur'], 5],
            ['Simran Kaur', 'simran@kaurrealty.in', '+91 91111 33344', 'Kaur & Associates Realty', 'New Delhi', 'Delhi', ['Residential', 'Commercial'], ['Dwarka', 'Aerocity'], 7],
        ];

        $active = [
            ['Arjun Reddy', 'arjun@vistahomes.in', '+91 92222 11122', 'Vista Homes Brokers', 'Bengaluru', 'Karnataka'],
            ['Neha Kulkarni', 'neha@kulkarniproperties.in', '+91 92222 22233', 'Kulkarni Property Consultants', 'Mumbai', 'Maharashtra'],
            ['Ramesh Chowdary', 'ramesh@chowdaryrealtors.in', '+91 92222 33344', 'Chowdary Realtors', 'Hyderabad', 'Telangana'],
            ['Aditya Malhotra', 'aditya@malhotraestates.in', '+91 92222 44455', 'Malhotra Estates', 'New Delhi', 'Delhi'],
        ];

        foreach ($pending as [$name, $email, $mobile, $company, $city, $state, $segments, $zones, $years]) {
            $user = User::updateOrCreate(['email' => $email], [
                'name' => $name,
                'password' => 'password',
                'role' => User::ROLE_BROKER,
                'status' => User::STATUS_PENDING,
                'mobile' => $mobile,
            ]);

            BrokerProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'is_company' => true,
                    'company_name' => $company,
                    'rera_number' => 'RERA/' . Str::upper(Str::substr($state, 0, 3)) . '/AGT/' . rand(10000, 99999),
                    'city' => $city,
                    'state' => $state,
                    'segments' => $segments,
                    'zones' => $zones,
                    'years_of_experience' => $years,
                    'confirm_accuracy' => true,
                    'submitted_at' => now()->subDays(rand(1, 6)),
                ]
            );
        }

        foreach ($active as $i => [$name, $email, $mobile, $company, $city, $state]) {
            $user = User::updateOrCreate(['email' => $email], [
                'name' => $name,
                'password' => 'password',
                'role' => User::ROLE_BROKER,
                'status' => User::STATUS_ACTIVE,
                'mobile' => $mobile,
                'email_verified_at' => now(),
            ]);

            BrokerProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'is_company' => true,
                    'company_name' => $company,
                    'rera_number' => 'RERA/' . Str::upper(Str::substr($state, 0, 3)) . '/AGT/' . rand(10000, 99999),
                    'city' => $city,
                    'state' => $state,
                    'segments' => ['Residential', 'Commercial'],
                    'years_of_experience' => 4 + $i,
                    'confirm_accuracy' => true,
                    'submitted_at' => now()->subMonths(2 + $i),
                ]
            );
        }
    }

    /** Downloads the given URL into the uploads disk and returns its relative path, or null on failure. */
    private function downloadImage(string $url, string $storagePath): ?string
    {
        try {
            $response = Http::timeout(20)->get($url);

            if (! $response->successful()) {
                return null;
            }

            \App\Support\FileStorage::diskForFolder(dirname($storagePath))->put($storagePath, $response->body());

            return $storagePath;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
