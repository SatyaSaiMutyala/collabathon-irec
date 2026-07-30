<?php

namespace Tests\Feature;

use App\Models\Developer;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The client's project sheet is ~70 fields spread over four tables. These cover the
 * seams that a form of that size gets wrong: fields landing on the wrong table, the
 * line-separated textareas that become JSON arrays, repeatable unit-type rows, and
 * uploads fanning out into `property_media` under the right `kind`.
 */
class ProjectIntakeTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $role = Role::create(['name' => 'Super Admin', 'is_system' => true]);

        return User::create([
            'name' => 'Ops',
            'email' => 'ops@example.test',
            'password' => 'password',
            'role' => User::ROLE_ADMIN,
            'role_id' => $role->id,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);
    }

    private function developer(): Developer
    {
        return Developer::create([
            'company_name' => 'Skyline Realty Group',
            'contact_person' => 'A. Rahman',
            'email' => 'sales@skyline.test',
            'city' => 'Dubai',
            'cp_payout_percent' => 2.5,
            'status' => 'active',
        ]);
    }

    public function test_the_full_project_sheet_is_persisted_across_all_four_tables(): void
    {
        Storage::fake('public');
        $developer = $this->developer();

        $response = $this->actingAs($this->superAdmin())->post('/admin/properties', [
            // 1 · basics
            'developer_id' => $developer->id,
            'name' => 'Azure Bay Residences',
            'project_type' => 'Residential',
            'project_status' => 'Under Construction',
            'listing_status' => 'active',
            'tagline' => 'Waterfront living',
            'description' => 'A detailed overview.',
            'logo' => UploadedFile::fake()->image('logo.png'),
            'rera_number' => 'RERA-DXB-24817',
            'rera_registered_at' => '2025-01-15',
            'rera_valid_till' => '2027-01-15',

            // 2 · location
            'state' => 'Dubai',
            'city' => 'Dubai',
            'locality' => 'Dubai Marina',
            'full_address' => 'Plot 44, Marina Walk',
            'landmark' => 'Opposite Marina Mall',
            'pincode' => '00000',
            'zone' => 'Central',
            'latitude' => 25.0762,
            'longitude' => 55.1390,
            'maps_link' => 'https://maps.app.goo.gl/abc',
            'connectivity_highlights' => "Metro — 800 m\n\nAirport — 22 km",
            'nearby_infrastructure' => "GEMS School — 1.2 km\nMediclinic — 2 km",

            // 3 · configuration & pricing
            'currency' => 'AED',
            'price_min' => 1_800_000,
            'price_max' => 3_200_000,
            'price_per_sqft' => 1450,
            'total_units' => 480,
            'towers' => 3,
            'floors_per_tower' => 24,
            'flats_per_floor' => 6,
            'parking_details' => '2 covered bays per unit',
            'unit_types' => [
                [
                    'label' => '2BHK',
                    'carpet_area_sqft' => 980,
                    'built_up_area_sqft' => 1150,
                    'super_built_up_area_sqft' => 1320,
                    'price_min' => 1_800_000,
                    'price_max' => 2_100_000,
                    'units_count' => 240,
                    'floor_plan' => UploadedFile::fake()->create('2bhk.pdf', 40, 'application/pdf'),
                ],
                // Blank template row the admin never filled in — must not become a record.
                ['label' => ''],
            ],
            'unit_plans' => [UploadedFile::fake()->image('layout.jpg')],

            // 4 · specifications
            'land_parcel_acres' => 12.5,
            'total_project_area_sqft' => 544_500,
            'open_space_percent' => 65,
            'construction_specifications' => 'RCC frame, porcelain flooring.',
            'amenities' => ['Clubhouse', 'Gym'],
            'amenities_extra' => 'Padel Court, Sky Lounge',
            'amenities_size' => '40,000 sq.ft. clubhouse',
            'amenities_count' => 24,
            'green_certification' => 'LEED Gold',
            'vastu_compliant' => '1',

            // 5 · timeline & legal
            'launch_date' => '2025-03-01',
            'possession_date' => '2027-12-31',
            'construction_progress' => 35,
            'approving_authorities' => "Dubai Municipality\nDDA",
            'bank_approvals' => "Emirates NBD\nADCB\nMashreq",

            // 6 · media
            'cover_image' => UploadedFile::fake()->image('cover.jpg'),
            'gallery' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
            'site_layout' => UploadedFile::fake()->image('site.png'),
            'master_plan' => UploadedFile::fake()->create('master.pdf', 60, 'application/pdf'),
            'brochure' => UploadedFile::fake()->create('brochure.pdf', 120, 'application/pdf'),
            'price_list' => UploadedFile::fake()->create('prices.pdf', 30, 'application/pdf'),
            'video_url' => 'https://youtube.com/watch?v=x',
            'virtual_tour_url' => 'https://my.matterport.com/show/?m=x',
            'payment_schedule_file' => UploadedFile::fake()->create('schedule.pdf', 20, 'application/pdf'),
            'payment_schedule' => '20% booking, 50% construction-linked, 30% handover.',

            // 7 · commercial terms
            'payment_plan_options' => ['Construction-linked', 'Flexi plan'],
            'booking_amount' => 100_000,
            'cp_commission_percent' => 2.75,
            'special_incentives' => 'Extra 0.5% until March.',
            'cashback_schemes' => '2% cashback on full payment.',
            'registration_stamp_duty' => '4% of value',
            'maintenance_charges' => 'AED 14 per sq.ft. / month',
            'floor_rise' => 'AED 15 per sq.ft. per floor',
            'plc_charges' => '3% park-facing',
            'other_charges' => "Club membership — AED 25,000\nLegal — AED 5,000",

            // 8 · contact & sales
            'sales_office_address' => 'Marina Plaza, Level 12',
            'site_visit_timings' => 'Mon–Sat, 10:00–19:00',
            'sales_contact_name' => 'Nadia K.',
            'sales_contact_number' => '+971 50 111 2233',
            'booking_process' => 'Passport, Emirates ID, 10% cheque.',

            // 9 · compliance
            'rera_certificate' => UploadedFile::fake()->image('rera-qr.png'),
            'legal_due_diligence' => UploadedFile::fake()->create('legal.pdf', 80, 'application/pdf'),
            'awards' => "Best Residential Project — Arabian Property Awards 2025",
        ]);

        $response->assertRedirect(route('admin.properties'));
        $response->assertSessionHasNoErrors();

        $property = Property::with(['detail', 'unitTypes', 'media'])->firstOrFail();

        // --- properties -------------------------------------------------------
        $this->assertSame('Azure Bay Residences', $property->name);
        $this->assertSame('Under Construction', $property->project_status);
        $this->assertSame('Central', $property->zone);
        $this->assertSame(1450, $property->price_per_sqft);
        $this->assertSame(24, $property->floors_per_tower);
        $this->assertSame(65, $property->open_space_percent);
        $this->assertSame(35, $property->construction_progress);
        $this->assertTrue($property->vastu_compliant);
        $this->assertSame('2027-01-15', $property->rera_valid_till->toDateString());
        $this->assertStringStartsWith('azure-bay-residences-', $property->slug);
        $this->assertNotNull($property->logo_path);
        $this->assertNotNull($property->cover_image_path);

        // --- property_details -------------------------------------------------
        $detail = $property->detail;
        $this->assertSame('2 covered bays per unit', $detail->parking_details);
        $this->assertSame('40,000 sq.ft. clubhouse', $detail->amenities_size);
        $this->assertSame('2.75', (string) $detail->cp_commission_percent);
        $this->assertSame('AED 15 per sq.ft. per floor', $detail->floor_rise);
        $this->assertSame('Nadia K.', $detail->sales_contact_name);
        $this->assertNotNull($detail->legal_due_diligence_path);

        // Line-separated textareas become JSON arrays; blank lines are dropped.
        $this->assertSame(['Metro — 800 m', 'Airport — 22 km'], $detail->connectivity_highlights);
        $this->assertSame(['Emirates NBD', 'ADCB', 'Mashreq'], $detail->bank_approvals);
        $this->assertSame(['Club membership — AED 25,000', 'Legal — AED 5,000'], $detail->other_charges);
        $this->assertSame(['Best Residential Project — Arabian Property Awards 2025'], $detail->awards);

        // Checkbox selections merge with free-typed extras.
        $this->assertSame(['Clubhouse', 'Gym', 'Padel Court', 'Sky Lounge'], $detail->amenities);
        $this->assertSame(['Construction-linked', 'Flexi plan'], $detail->payment_plan_options);

        // --- property_unit_types ---------------------------------------------
        $this->assertCount(1, $property->unitTypes, 'The blank template row must be skipped.');
        $unit = $property->unitTypes->first();
        $this->assertSame('2BHK', $unit->label);
        $this->assertSame(1320, $unit->super_built_up_area_sqft);
        $this->assertSame(240, $unit->units_count);
        $this->assertNotNull($unit->floor_plan_path);

        // --- property_media ---------------------------------------------------
        $byKind = $property->media->groupBy('kind');
        $this->assertCount(2, $byKind['image']);
        $this->assertSame([0, 1], $byKind['image']->pluck('sort_order')->all());
        foreach (['unit_plan', 'site_layout', 'master_plan', 'brochure', 'price_list', 'rera_certificate', 'payment_schedule'] as $kind) {
            $this->assertCount(1, $byKind[$kind] ?? [], "Missing media of kind [{$kind}].");
        }

        // Links are stored as url, never as a disk path.
        $this->assertSame('https://youtube.com/watch?v=x', $byKind['video']->first()->url);
        $this->assertNull($byKind['video']->first()->path);
        $this->assertSame('https://my.matterport.com/show/?m=x', $byKind['virtual_tour']->first()->url);

        // Every upload is grouped under its property id.
        foreach ($property->media->whereNotNull('path') as $media) {
            Storage::disk('public')->assertExists($media->path);
            $this->assertStringStartsWith("properties/{$property->id}/", $media->path);
        }
    }

    public function test_only_the_basics_are_required(): void
    {
        $developer = $this->developer();

        $this->actingAs($this->superAdmin())->post('/admin/properties', [
            'developer_id' => $developer->id,
            'name' => 'Bare Minimum Tower',
            'project_type' => 'Residential',
            'project_status' => 'New Launch',
            'listing_status' => 'draft',
            'city' => 'Dubai',
            'currency' => 'AED',
            'price_min' => 500_000,
            'price_max' => 900_000,
        ])->assertSessionHasNoErrors();

        $property = Property::with('detail')->firstOrFail();

        $this->assertSame('draft', $property->listing_status);
        $this->assertSame(0, $property->construction_progress);
        $this->assertFalse($property->vastu_compliant);
        // An untouched list field reads as "not provided", not an empty array.
        $this->assertNull($property->detail->amenities);
        $this->assertNull($property->detail->bank_approvals);
    }

    public function test_price_ceiling_below_the_floor_is_rejected(): void
    {
        $developer = $this->developer();

        $this->actingAs($this->superAdmin())->post('/admin/properties', [
            'developer_id' => $developer->id,
            'name' => 'Inverted Pricing',
            'project_type' => 'Residential',
            'project_status' => 'New Launch',
            'listing_status' => 'draft',
            'city' => 'Dubai',
            'currency' => 'AED',
            'price_min' => 900_000,
            'price_max' => 500_000,
        ])->assertSessionHasErrors('price_max');

        $this->assertSame(0, Property::count());
    }

    public function test_developer_intake_captures_the_trust_signal_fields(): void
    {
        Storage::fake('public');

        $this->actingAs($this->superAdmin())->post('/admin/developers', [
            'company_name' => 'Meridian Estates',
            'contact_person' => 'S. Iyer',
            'email' => 'hello@meridian.test',
            'mobile' => '+971 50 999 8877',
            'city' => 'Abu Dhabi',
            'state' => 'Abu Dhabi',
            'rera_number' => 'RERA-AUH-9931',
            'logo' => UploadedFile::fake()->image('meridian.png'),
            'about' => 'Twelve years, nine delivered communities.',
            'cp_payout_percent' => 3.25,
            'verified' => '1',
            'status' => 'active',
        ])->assertSessionHasNoErrors();

        $developer = Developer::where('company_name', 'Meridian Estates')->firstOrFail();

        $this->assertSame('Abu Dhabi', $developer->state);
        $this->assertSame('RERA-AUH-9931', $developer->rera_number);
        $this->assertTrue($developer->verified);
        $this->assertStringContainsString('nine delivered communities', $developer->about);
        Storage::disk('public')->assertExists($developer->logo_path);

        // The login account is issued alongside the company record.
        $this->assertSame(User::ROLE_DEVELOPER, $developer->user->role);

        // The list surfaces the uploaded logo and the verified badge.
        $list = $this->get('/admin/developers');
        $list->assertOk();
        $list->assertSee(Storage::disk('public')->url($developer->logo_path), false);
    }

    public function test_an_unchecked_switch_posts_false_rather_than_dropping_the_field(): void
    {
        $this->actingAs($this->superAdmin())->post('/admin/developers', [
            'company_name' => 'Unverified Holdings',
            'contact_person' => 'T. Okafor',
            'email' => 'ops@unverified.test',
            'mobile' => '+971 50 222 3344',
            'city' => 'Sharjah',
            'cp_payout_percent' => 2,
            'verified' => '0',
            'status' => 'active',
        ])->assertSessionHasNoErrors();

        $this->assertFalse(Developer::where('company_name', 'Unverified Holdings')->firstOrFail()->verified);
    }

    /**
     * The form posts with `novalidate`, so a required field left blank on a step the admin
     * never opened reaches the server. It must come back as an error, and the wizard must
     * reopen on that field's step rather than on step 1.
     */
    public function test_a_blank_required_field_on_a_later_step_reopens_the_wizard_there(): void
    {
        $developer = $this->developer();
        $admin = $this->superAdmin();

        $payload = [
            'developer_id' => $developer->id,
            'name' => 'No City Tower',
            'project_type' => 'Residential',
            'project_status' => 'New Launch',
            'listing_status' => 'active',
            'currency' => 'AED',
            'price_min' => 500_000,
            'price_max' => 900_000,
            // `city` (step 2) omitted.
        ];

        $this->actingAs($admin)
            ->from('/admin/properties/create')
            ->post('/admin/properties', $payload)
            ->assertRedirect('/admin/properties/create')
            ->assertSessionHasErrors('city');

        $this->assertSame(0, Property::count());

        // Following the redirect renders the wizard with the errors shared into the view.
        $reopened = $this->actingAs($admin)
            ->from('/admin/properties/create')
            ->followingRedirects()
            ->post('/admin/properties', $payload);

        $reopened->assertOk();
        $reopened->assertSee('The city field is required.');
        // `city` sits on step 2, so that is where the wizard must open.
        $reopened->assertSee('step: 2,', false);
    }

    public function test_the_intake_form_renders_every_step(): void
    {
        $this->developer();

        $response = $this->actingAs($this->superAdmin())->get('/admin/properties/create');

        $response->assertOk();
        foreach (['Project basic info', 'Location details', 'Configuration &amp; pricing',
                  'Project specifications', 'Timeline &amp; legal', 'Media &amp; marketing assets',
                  'Commercial terms', 'Contact &amp; sales info', 'Compliance &amp; trust signals'] as $heading) {
            $response->assertSee($heading, false);
        }

        // Every step must be reachable by the submit guard, which locates the offending
        // control via the nearest [data-step] ancestor.
        foreach (range(1, 9) as $step) {
            $response->assertSee('data-step="' . $step . '"', false);
        }

        // The publish choice must be a hidden field, not the submit button's name/value:
        // submit() ignores the submitter, so a button-borne value would be lost. Asserted on
        // the attributes rather than the whole tag, which wraps across lines.
        $response->assertSee('name="listing_status"', false);
        $response->assertSee('x-ref="publish"', false);
        $response->assertDontSee('name="listing_status" value="active"', false);

        // The client-side size check needs the server's real ceilings, not hard-coded ones.
        $response->assertSee('maxPost: ' . (($this->iniBytes('post_max_size') ?: PHP_INT_MAX) - 512 * 1024), false);
        $response->assertSee('maxFile: ' . ($this->iniBytes('upload_max_filesize') ?: PHP_INT_MAX), false);
    }

    private function iniBytes(string $key): int
    {
        $size = (string) ini_get($key);
        $number = (int) $size;

        return match (strtolower(substr(trim($size), -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
