<?php

namespace Database\Seeders;

use App\Models\ApprovalDecision;
use App\Models\BrokerProfile;
use App\Models\Developer;
use App\Models\FormField;
use App\Models\Lead;
use App\Models\Property;
use App\Models\PropertyDetail;
use App\Models\PropertyMedia;
use App\Models\PropertyUnitType;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Ports the two former mock sources — the admin panel's AdminMockData and the mobile
 * app's mockDevelopers/mockLeads — into MySQL so both clients read the same rows.
 *
 * Idempotent: every write is updateOrCreate, so re-running never duplicates.
 * Every seeded account uses the password "password".
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = $this->seedAdmin();
        $developers = $this->seedDevelopers();
        $this->seedProperties($developers);
        $brokers = $this->seedBrokers($admin);
        $this->seedLeads($brokers);
        $this->seedSettings();
    }

    private function seedAdmin(): User
    {
        $superAdmin = Role::updateOrCreate(['name' => 'Super Admin'], ['is_system' => true]);

        // Cosmetic parity for the Roles UI — Gate::before already bypasses every
        // check for is_system regardless, but a 0/6 matrix would look broken.
        foreach (Role::MODULES as $module => $label) {
            RolePermission::updateOrCreate(
                ['role_id' => $superAdmin->id, 'module' => $module],
                ['can_view' => true, 'can_edit' => true, 'can_delete' => true]
            );
        }

        return User::updateOrCreate(
            ['email' => 'admin@irec.ae'],
            [
                'name' => 'Admin',
                'password' => 'password',
                'role' => User::ROLE_ADMIN,
                'role_id' => $superAdmin->id,
                'status' => User::STATUS_ACTIVE,
                'mobile' => '+971 50 000 1122',
                'email_verified_at' => now(),
            ]
        );
    }

    /** @return array<string,Developer> keyed by company name */
    private function seedDevelopers(): array
    {
        $rows = [
            ['Skyline Realty Group', 'Ahmed Al Farsi', '+971 50 123 4567', 'ahmed@skylinerealty.ae', 'Dubai', 2.5, true],
            ['Meridian Builders', 'Fatima Noor', '+971 55 987 6543', 'fatima@meridianbuilders.ae', 'Abu Dhabi', 3.0, true],
            ['Palm Coast Developers', 'Rakesh Menon', '+971 52 456 7890', 'rakesh@palmcoast.ae', 'Dubai', 2.0, true],
            ['Horizon Estates', 'Layla Haddad', '+971 56 321 0987', 'layla@horizonestates.ae', 'Sharjah', 2.75, false],
            ['Crescent Land Co.', 'Omar Siddiqui', '+971 54 220 4411', 'omar@crescentland.ae', 'Ajman', 3.25, false],
        ];

        $out = [];

        foreach ($rows as [$company, $contact, $mobile, $email, $city, $payout, $verified]) {
            // Each developer gets a login account — admin-issued, never self-registered.
            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $contact,
                    'password' => 'password',
                    'role' => User::ROLE_DEVELOPER,
                    'status' => User::STATUS_ACTIVE,
                    'mobile' => $mobile,
                    'email_verified_at' => now(),
                ]
            );

            $out[$company] = Developer::updateOrCreate(
                ['company_name' => $company],
                [
                    'user_id' => $user->id,
                    'contact_person' => $contact,
                    'mobile' => $mobile,
                    'email' => $email,
                    'city' => $city,
                    'state' => 'UAE',
                    'cp_payout_percent' => $payout,
                    'verified' => $verified,
                    'status' => $company === 'Crescent Land Co.' ? 'paused' : 'active',
                    'about' => $company . ' delivers residential and commercial projects across the UAE.',
                ]
            );
        }

        return $out;
    }

    /** @param  array<string,Developer>  $developers */
    private function seedProperties(array $developers): void
    {
        $rows = [
            ['Azure Bay Residences', 'Skyline Realty Group', 'Dubai Marina', 'Residential', 'active', 1_800_000, 3_200_000, 148, 12],
            ['The Meridian Tower', 'Meridian Builders', 'Al Reem Island', 'Residential', 'active', 950_000, 1_600_000, 96, 7],
            ['Palm Coast Villas', 'Palm Coast Developers', 'Palm Jumeirah', 'Villa', 'active', 6_500_000, 11_000_000, 203, 19],
            ['Horizon Business Bay', 'Horizon Estates', 'Business Bay', 'Commercial', 'draft', 2_100_000, 4_000_000, 0, 0],
            ['Crescent Gardens', 'Crescent Land Co.', 'Ajman Corniche', 'Plotted Development', 'active', 480_000, 900_000, 61, 4],
            ['Skyline Heights', 'Skyline Realty Group', 'JLT', 'Residential', 'archived', 1_100_000, 2_000_000, 132, 9],
        ];

        foreach ($rows as $i => [$name, $company, $locality, $type, $status, $min, $max, $views, $interests]) {
            $property = Property::updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'developer_id' => $developers[$company]->id,
                    'name' => $name,
                    'project_type' => $type,
                    'project_status' => $status === 'active' ? 'Under Construction' : 'New Launch',
                    'listing_status' => $status,
                    'tagline' => 'Live above the skyline',
                    'description' => "A landmark development in {$locality} offering panoramic views, "
                        . 'private balconies and resort-style amenities.',
                    'rera_number' => 'RERA-PRJ-' . (10000 + $i),
                    'state' => 'UAE',
                    'city' => explode(' ', $locality)[0],
                    'locality' => $locality,
                    'zone' => ['East', 'West', 'North', 'South', 'Central'][$i % 5],
                    'pincode' => '0000' . $i,
                    'price_min' => $min,
                    'price_max' => $max,
                    'price_per_sqft' => (int) round($min / 250),
                    'currency' => 'AED',
                    'total_units' => 120 + $i * 40,
                    'towers' => 2,
                    'floors_per_tower' => 30 + $i,
                    'flats_per_floor' => 6,
                    'land_parcel_acres' => 4.5,
                    'total_project_area_sqft' => 180000,
                    'open_space_percent' => 40,
                    'launch_date' => now()->subMonths(18 - $i)->toDateString(),
                    'possession_date' => now()->addMonths(12 + $i * 3)->toDateString(),
                    'construction_progress' => $status === 'active' ? 35 + $i * 5 : 0,
                    'vastu_compliant' => true,
                    'views_count' => $views,
                    'interests_count' => $interests,
                ]
            );

            PropertyDetail::updateOrCreate(
                ['property_id' => $property->id],
                [
                    'connectivity_highlights' => ['Metro 800m', 'Airport 22km', 'Highway access 1.2km'],
                    'nearby_infrastructure' => ['GEMS School 1.5km', 'Mediclinic 2km', 'Marina Mall 3km'],
                    'construction_specifications' => 'RCC frame, vitrified flooring, branded CP fittings.',
                    'amenities' => ['Infinity Pool', 'Gym', 'Concierge', 'Parking', 'Kids Play Area', 'CCTV'],
                    'amenities_count' => 6,
                    'parking_details' => 'Covered, 1 bay per unit',
                    'approving_authorities' => ['Dubai Municipality', 'RERA'],
                    'bank_approvals' => ['Emirates NBD', 'ADCB', 'Mashreq'],
                    'payment_plan_options' => ['Construction-linked', 'Down payment', 'Flexi plan'],
                    'booking_amount' => 50000,
                    'cp_commission_percent' => 2.5,
                    'maintenance_charges' => 'AED 14 / sq.ft. / year',
                    'sales_office_address' => "{$locality} Sales Gallery",
                    'site_visit_timings' => 'Daily 10:00 – 19:00',
                    'sales_contact_name' => 'Sales Desk',
                    'sales_contact_number' => '+971 4 000 0000',
                ]
            );

            $property->unitTypes()->delete();
            foreach ([['2BHK', 1250], ['3BHK', 1620], ['4BHK', 2100]] as $j => [$label, $carpet]) {
                PropertyUnitType::create([
                    'property_id' => $property->id,
                    'label' => $label,
                    'carpet_area_sqft' => $carpet,
                    'built_up_area_sqft' => (int) ($carpet * 1.15),
                    'super_built_up_area_sqft' => (int) ($carpet * 1.3),
                    'price_min' => $min + $j * 250_000,
                    'price_max' => $min + $j * 250_000 + 400_000,
                    'units_count' => 40,
                    'sort_order' => $j,
                ]);
            }

            $property->media()->delete();
            $seed = Str::slug($name);
            foreach (range(1, 4) as $k) {
                PropertyMedia::create([
                    'property_id' => $property->id,
                    'kind' => 'image',
                    'url' => "https://picsum.photos/seed/{$seed}-{$k}/900/700",
                    'sort_order' => $k,
                ]);
            }
        }
    }

    /** @return array<int,User> the active brokers that leads hang off */
    private function seedBrokers(User $admin): array
    {
        // Pending — these populate the admin approvals queue.
        $pending = [
            ['Sana Iqbal', 'sana@iqbalrealty.ae', '+971 58 111 2233', 'Iqbal Realty Partners', 'RERA-88213', 'Dubai', ['Residential', 'Commercial'], ['Dubai Marina', 'JLT'], 6],
            ['Marco Silva', 'marco@silvaco.ae', '+971 50 444 5566', 'Silva & Co Properties', 'RERA-77410', 'Abu Dhabi', ['Residential'], ['Al Reem Island'], 3],
            ['Priya Nair', 'priya@nairproperty.ae', '+971 52 778 8899', 'Nair Property Consultants', 'RERA-90045', 'Dubai', ['Commercial', 'Land'], ['Business Bay', 'Downtown'], 11],
            ['Hassan Tariq', 'hassan@tariqrealty.ae', '+971 55 909 1212', 'Tariq Realty', 'RERA-81190', 'Sharjah', ['Residential'], ['Al Majaz'], 2],
        ];

        // Already approved — these are the brokers who generate leads.
        $active = [
            ['Rohit Sharma', 'rohit@skylinerealty.com', '+971 50 111 2222', 'Skyline Realty', 'RERA-11223', 'Dubai'],
            ['Aisha Rahman', 'aisha@eliteprop.com', '+971 50 222 3333', 'Elite Properties', 'RERA-22334', 'Dubai'],
            ['Daniel Cho', 'daniel@urbannest.com', '+971 50 333 4444', 'Urban Nest Realty', 'RERA-33445', 'Abu Dhabi'],
            ['Nadia Hussain', 'nadia@horizonhomes.com', '+971 50 444 5555', 'Horizon Homes', 'RERA-44556', 'Dubai'],
        ];

        $created = [];

        foreach ($pending as [$name, $email, $mobile, $company, $rera, $city, $segments, $zones, $years]) {
            $user = User::updateOrCreate(['email' => $email], [
                'name' => $name,
                'password' => 'password',
                'role' => User::ROLE_BROKER,
                'status' => User::STATUS_PENDING,
                'mobile' => $mobile,
            ]);

            BrokerProfile::updateOrCreate(['user_id' => $user->id], [
                'is_company' => true,
                'company_name' => $company,
                'rera_number' => $rera,
                'rera_certificate_expiry' => now()->addYear()->toDateString(),
                'city' => $city,
                'state' => 'UAE',
                'segments' => $segments,
                'zones' => $zones,
                'years_of_experience' => $years,
                'confirm_accuracy' => true,
                'submitted_at' => now()->subDays(rand(1, 7)),
            ]);
        }

        foreach ($active as [$name, $email, $mobile, $company, $rera, $city]) {
            $user = User::updateOrCreate(['email' => $email], [
                'name' => $name,
                'password' => 'password',
                'role' => User::ROLE_BROKER,
                'status' => User::STATUS_ACTIVE,
                'mobile' => $mobile,
                'email_verified_at' => now(),
            ]);

            BrokerProfile::updateOrCreate(['user_id' => $user->id], [
                'is_company' => true,
                'company_name' => $company,
                'rera_number' => $rera,
                'city' => $city,
                'state' => 'UAE',
                'segments' => ['Residential'],
                'confirm_accuracy' => true,
                'submitted_at' => now()->subMonths(3),
            ]);

            ApprovalDecision::updateOrCreate(
                ['user_id' => $user->id, 'decision' => 'approved'],
                ['decided_by' => $admin->id, 'reason' => null]
            );

            $created[] = $user;
        }

        // One rejected broker so the admin "Decided" tab shows both outcomes.
        $rejected = User::updateOrCreate(['email' => 'karan@mehtaestates.ae'], [
            'name' => 'Karan Mehta',
            'password' => 'password',
            'role' => User::ROLE_BROKER,
            'status' => User::STATUS_REJECTED,
            'mobile' => '+971 55 777 8888',
        ]);

        BrokerProfile::updateOrCreate(['user_id' => $rejected->id], [
            'company_name' => 'Mehta Estates',
            'rera_number' => 'RERA-00000',
            'city' => 'Dubai',
            'confirm_accuracy' => true,
            'submitted_at' => now()->subDays(10),
        ]);

        ApprovalDecision::updateOrCreate(
            ['user_id' => $rejected->id, 'decision' => 'rejected'],
            ['decided_by' => $admin->id, 'reason' => 'RERA number could not be verified']
        );

        return $created;
    }

    /** @param  array<int,User>  $brokers */
    private function seedLeads(array $brokers): void
    {
        $properties = Property::where('listing_status', 'active')->get();

        if ($properties->isEmpty() || $brokers === []) {
            return;
        }

        $statuses = [Lead::STATUS_ACCEPTED, Lead::STATUS_INTERESTED, Lead::STATUS_VIEWED, Lead::STATUS_DECLINED];

        foreach ($brokers as $bi => $broker) {
            foreach ($properties as $pi => $property) {
                // Skip a few so the matrix isn't a perfect grid.
                if (($bi + $pi) % 3 === 2) {
                    continue;
                }

                $status = $statuses[($bi + $pi) % count($statuses)];
                // Contact unlocks at "interested" and stays unlocked thereafter.
                $unlocked = $status !== Lead::STATUS_VIEWED;

                // Spread across the last 12 weeks. created_at drives the dashboard's
                // weekly grouping — leaving it at "now" collapses the trend into one
                // vertical cliff on the final week.
                $weeksAgo = 11 - (($bi * 3 + $pi * 2) % 12);
                $created = now()->subWeeks($weeksAgo)->subDays($pi % 7);

                $lead = Lead::updateOrCreate(
                    ['property_id' => $property->id, 'broker_id' => $broker->id],
                    [
                        'developer_id' => $property->developer_id,
                        'status' => $status,
                        'contact_unlocked' => $unlocked,
                        'viewed_at' => $created,
                        'interested_at' => $unlocked ? $created->copy()->addHours(6) : null,
                        'responded_at' => in_array($status, [Lead::STATUS_ACCEPTED, Lead::STATUS_DECLINED], true)
                            ? $created->copy()->addDay()
                            : null,
                    ]
                );

                // timestamps are managed, so backdate created_at explicitly.
                $lead->forceFill(['created_at' => $created, 'updated_at' => $created])->saveQuietly();
            }
        }
    }

    private function seedSettings(): void
    {
        Setting::put('accent_color', '#C9A227');
        Setting::put('app_name', 'iREC');

        $broker = [
            ['full_name', 'Full Name', true, true, true],
            ['mobile', 'Mobile Number', true, true, true],
            ['email', 'Email', true, true, true],
            ['company_name', 'Company Name', true, false, false],
            ['rera_number', 'RERA Number', true, true, true],
            ['gst_number', 'GST Number', true, false, false],
            ['years_of_experience', 'Years of Experience', false, false, false],
        ];

        foreach ($broker as $i => [$key, $label, $enabled, $required, $core]) {
            FormField::updateOrCreate(
                ['form' => FormField::FORM_BROKER, 'field_key' => $key],
                ['label' => $label, 'enabled' => $enabled, 'required' => $required, 'is_core' => $core, 'sort_order' => $i]
            );
        }

        $property = [
            ['project_name', 'Project Name', true, true, true],
            ['price_range', 'Price Range', true, true, true],
            ['location', 'Location', true, true, true],
            ['amenities', 'Amenities', true, false, false],
            ['cp_commission', 'CP Commission %', true, true, false],
            ['construction_progress', 'Construction Progress', false, false, false],
        ];

        foreach ($property as $i => [$key, $label, $enabled, $required, $core]) {
            FormField::updateOrCreate(
                ['form' => FormField::FORM_PROPERTY, 'field_key' => $key],
                ['label' => $label, 'enabled' => $enabled, 'required' => $required, 'is_core' => $core, 'sort_order' => $i]
            );
        }
    }
}
