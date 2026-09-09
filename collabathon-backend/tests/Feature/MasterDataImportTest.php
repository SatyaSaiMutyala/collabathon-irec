<?php

namespace Tests\Feature;

use App\Models\Amenity;
use App\Support\ImageCompressor;
use App\Models\Country;
use App\Models\Developer;
use App\Models\MeasurementUnit;
use App\Models\ProjectType;
use App\Models\Property;
use App\Models\Role;
use App\Models\UnitType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Importing an irecexpo.com registration.
 *
 * The case worth pinning down is the one that used to fail outright: a developer files
 * one registration per project, so their second project arrives carrying the same email
 * and mobile as their first. That used to hit the duplicate-account guard and stop, and
 * the fix is that it now resolves to the account already on the platform and brings
 * only the listing.
 *
 * The vendor API is never reached here. `convert()` reads its record from the same
 * cache `index()` fills, so seeding that cache exercises the whole HTTP path — the
 * controller, the importer and the mappers — without a network call.
 */
class MasterDataImportTest extends TestCase
{
    use RefreshDatabase;

    /** Whether this test has already stubbed the asset host — see convert(). */
    private bool $assetHostFaked = false;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('uploads');
    }

    /**
     * Every asset the mappers find is fetched over HTTP, so the client is faked to keep
     * the suite off the network. Registered per test rather than once in setUp: repeated
     * Http::fake() calls stack and the first matching stub wins, so a test that needs
     * failing downloads could never override a blanket success stub set up here.
     */
    private function fakeAssetHost(int $status = 200): void
    {
        $this->assetHostFaked = true;

        // A closure, not a bare Http::response(). The downloader streams each body into a
        // sink file, which consumes the response's stream — and a single stubbed response
        // instance is handed back for every match, so its stream is empty from the second
        // asset onwards. Building a fresh one per request is what a real host does.
        Http::fake([
            '*' => fn () => Http::response('binary-image-bytes', $status, ['Content-Type' => 'image/jpeg']),
        ]);
    }

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

    /**
     * One registration as the vendor sends it. `$overrides` is merged over the top so a
     * test can vary the one field it cares about without restating the payload.
     */
    private function registration(int $id, array $overrides = []): array
    {
        return array_replace_recursive([
            'registration_id' => $id,
            'reference_code' => "IREC-DEV-2026-{$id}",
            'developer_profile' => [
                'company_name' => 'EIPL Constructions',
                'key_contact_person' => 'Ratna Prasad',
                'designation' => 'GM - Sales & Marketing',
                'email' => 'ratnaprasad@eiplgroup.test',
                'mobile' => '+91 9949155551',
                'website' => 'https://www.eipltreasuretrove.test/',
                'registered_address' => 'Plot No. 17, Gandipet Road',
                'city' => 'Hyderabad',
                'state' => 'Telangana',
                'country' => 'India',
                'pincode' => '500075',
                'builder_logo_url' => 'https://cdn.irecexpo.test/logo.png',
                'social_links' => ['instagram' => 'https://instagram.test/eipl'],
            ],
            'project_details' => [
                'project_name' => 'Treasure Trove',
                'project_type' => 'residential',
                'project_status' => 'ongoing',
                'possession_date' => '2027-12-01',
                'price_starts_from_inr' => '₹ 1.2 Cr',
                'title_tagline' => 'Homes above the city',
                'project_description' => 'A gated community.',
                'locality_area' => 'Narsingi',
                'zone' => 'west',
                'total_units' => '480',
                'blocks_towers' => '4',
                'number_of_floors' => '22',
                'land_parcel_acres' => '12.5',
                'connectivity_highlights' => "Metro 800 m\nORR 2 km",
                'amenities_list' => ['Clubhouse', 'Gym', 'Swimming Pool'],
                'geo_coordinates' => ['latitude' => 17.4001, 'longitude' => 78.3201, 'maps_link' => 'https://maps.test/tt'],
                'dynamic_unit_configurations' => [
                    ['bhk' => '3 BHK', 'size' => '1,850', 'units' => '240', 'price' => '1.2 Cr'],
                    ['bhk' => '4 BHK', 'size' => '2400', 'units' => '120', 'price' => '1.85 Cr'],
                ],
                'channel_partner_commercials' => [
                    'cp_commission' => '3.5',
                    'fos_commission' => '25000',
                    'sales_contact_name' => 'Sales Desk',
                    'sales_contact_number' => '9949155552',
                ],
            ],
            'visual_assets' => [
                'hero_image_url' => 'https://cdn.irecexpo.test/hero.jpg',
                'gallery_images' => [
                    ['image_url' => 'https://cdn.irecexpo.test/g1.jpg', 'caption' => 'Elevation'],
                    ['image_url' => 'https://cdn.irecexpo.test/g2.jpg', 'caption' => 'Clubhouse'],
                ],
            ],
            'documents' => ['brochure_url' => 'https://cdn.irecexpo.test/brochure.pdf'],
            'sync_meta' => ['status' => 'approved', 'created_at' => '2026-09-04 10:00:00'],
        ], $overrides);
    }

    /** Puts a record where the controller looks for it, standing in for the vendor call. */
    private function seedRecord(array $record): array
    {
        Cache::put("master-data:record:{$record['registration_id']}", $record, now()->addMinutes(20));

        return $record;
    }

    /**
     * Posts the convert action, arranging a working asset host first unless the test
     * has already stubbed one of its own — which is what lets the failed-download test
     * register a failing stub without this one shadowing it.
     */
    private function convert(User $admin, array $record)
    {
        if (! $this->assetHostFaked) {
            $this->fakeAssetHost();
        }

        return $this->actingAs($admin)->post("/admin/master-data/{$record['registration_id']}/convert");
    }

    public function test_a_first_registration_creates_the_developer_account_and_the_listing(): void
    {
        $record = $this->seedRecord($this->registration(1));

        $this->convert($this->superAdmin(), $record)->assertRedirect();

        $developer = Developer::where('company_name', 'EIPL Constructions')->first();
        $this->assertNotNull($developer);
        $this->assertSame('IREC-DEV-2026-1', $developer->external_reference_code);
        $this->assertSame('9949155551', $developer->mobile, 'the +91 prefix is stripped to the stored 10 digits');

        $user = $developer->user;
        $this->assertSame(User::ROLE_DEVELOPER, $user->role);
        $this->assertSame(User::STATUS_ACTIVE, $user->status);

        $property = Property::where('external_reference_code', 'IREC-DEV-2026-1')->first();
        $this->assertNotNull($property, 'the project itself must come across, not only the account');
        $this->assertSame($developer->id, $property->developer_id);
        $this->assertSame('Treasure Trove', $property->name);
        $this->assertSame('active', $property->listing_status);
        $this->assertSame(
            Property::DEV_PENDING,
            $property->developer_status,
            'live still waits on the developer accepting — an import must not bypass that'
        );
    }

    /**
     * The regression this whole change exists for. Before it, the second registration
     * was rejected with "email is already in use by another account".
     */
    public function test_a_second_registration_from_the_same_developer_adds_another_listing(): void
    {
        $admin = $this->superAdmin();

        $this->convert($admin, $this->seedRecord($this->registration(1)))->assertRedirect();

        $second = $this->seedRecord($this->registration(2, [
            'project_details' => ['project_name' => 'Treasure Trove Phase II'],
        ]));

        $this->convert($admin, $second)->assertSessionHasNoErrors();

        $this->assertSame(1, Developer::count(), 'the company is not duplicated');
        $this->assertSame(1, User::where('role', User::ROLE_DEVELOPER)->count(), 'nor is their login');

        $developer = Developer::first();
        $this->assertSame(2, $developer->properties()->count());
        $this->assertEqualsCanonicalizing(
            ['Treasure Trove', 'Treasure Trove Phase II'],
            $developer->properties()->pluck('name')->all()
        );
    }

    public function test_a_developer_already_added_by_hand_gains_the_listing_rather_than_a_duplicate_account(): void
    {
        $admin = $this->superAdmin();

        // Created in the admin panel first, so it carries no reference code at all —
        // the case that can only resolve on the identity fields.
        $user = User::create([
            'name' => 'Ratna Prasad',
            'email' => 'ratnaprasad@eiplgroup.test',
            'password' => 'password',
            'mobile' => '9949155551',
            'role' => User::ROLE_DEVELOPER,
            'status' => User::STATUS_ACTIVE,
        ]);
        $existing = Developer::create([
            'user_id' => $user->id,
            'company_name' => 'EIPL Constructions',
            'contact_person' => 'Ratna Prasad',
            'email' => 'ratnaprasad@eiplgroup.test',
            'mobile' => '9949155551',
            'city' => 'Hyderabad',
            'cp_payout_percent' => 2.5,
            'status' => 'active',
        ]);

        $this->convert($admin, $this->seedRecord($this->registration(7)))->assertSessionHasNoErrors();

        $this->assertSame(1, Developer::count());
        $this->assertSame(1, $existing->properties()->count());
    }

    public function test_converting_the_same_registration_twice_does_not_duplicate_the_listing(): void
    {
        $admin = $this->superAdmin();
        $record = $this->seedRecord($this->registration(3));

        $this->convert($admin, $record);
        $this->convert($admin, $record);

        $this->assertSame(1, Property::count());
        $this->assertSame(1, Developer::count());
    }

    public function test_an_email_already_held_by_a_broker_is_refused_with_a_reason(): void
    {
        User::create([
            'name' => 'A Broker',
            'email' => 'ratnaprasad@eiplgroup.test',
            'password' => 'password',
            'role' => User::ROLE_BROKER,
            'status' => User::STATUS_ACTIVE,
        ]);

        $record = $this->seedRecord($this->registration(4));

        $this->convert($this->superAdmin(), $record)
            ->assertRedirect(route('admin.master-data.show', 4))
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'broker account'));

        $this->assertSame(0, Property::count(), 'a refused import leaves nothing behind');
        $this->assertSame(0, Developer::count());
    }

    public function test_a_developer_in_trash_is_named_so_the_admin_can_restore_rather_than_duplicate(): void
    {
        $admin = $this->superAdmin();
        $this->convert($admin, $this->seedRecord($this->registration(9)));

        Developer::firstOrFail()->delete();

        $this->convert($admin, $this->seedRecord($this->registration(10)))
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'is in Trash'));

        $this->assertSame(0, Developer::count(), 'no second account is created around the deleted one');
    }

    /**
     * The vendor's project_type is free text and in practice holds sub-brand names
     * ("Sky Mansions"), so refusing an unknown one would stop nearly every import.
     */
    public function test_an_unknown_project_type_is_added_to_settings_rather_than_blocking_the_import(): void
    {
        $record = $this->seedRecord($this->registration(5, [
            'project_details' => ['project_type' => 'Sky Mansions'],
        ]));

        $this->convert($this->superAdmin(), $record)->assertSessionHasNoErrors();

        $added = ProjectType::where('name', 'Sky Mansions')->first();
        $this->assertNotNull($added);
        $this->assertFalse(
            $added->is_active,
            'added switched off, so a developer sub-brand never reaches the intake form or a broker filter'
        );
        $this->assertSame('Sky Mansions', Property::firstOrFail()->project_type);
    }

    public function test_the_admin_is_told_what_the_import_added_to_their_lists(): void
    {
        $record = $this->seedRecord($this->registration(11, [
            'project_details' => ['project_type' => 'Sky Mansions'],
        ]));

        $this->convert($this->superAdmin(), $record)
            ->assertSessionHas('warning', fn ($note) => str_contains($note, 'Sky Mansions')
                && str_contains($note, 'project types'));
    }

    public function test_a_known_value_in_the_vendors_casing_resolves_to_ours_instead_of_being_added_twice(): void
    {
        $before = ProjectType::count();

        $this->convert($this->superAdmin(), $this->seedRecord($this->registration(12)));

        $this->assertSame($before, ProjectType::count(), '"residential" is the "Residential" we already have');
        $this->assertSame('Residential', Property::firstOrFail()->project_type);
    }

    /** Amenities, unit labels, the extent metric and the location tree follow the same rule. */
    public function test_the_other_free_text_lists_are_topped_up_from_the_registration(): void
    {
        $this->convert($this->superAdmin(), $this->seedRecord($this->registration(13, [
            'project_details' => ['extent_metric' => 'Acres'],
        ])));

        $this->assertTrue(Amenity::where('name', 'Clubhouse')->exists());
        $this->assertTrue(MeasurementUnit::where('name', 'Acres')->exists());

        // "3 BHK" is not added beside the seeded "3BHK" — spacing and punctuation are
        // ignored when matching, so a near-miss tops the list up rather than doubling it.
        $this->assertSame(0, UnitType::where('name', '3 BHK')->count());
        $this->assertSame(1, UnitType::where('name', '3BHK')->count());

        // Country → state → city, created top-down.
        $country = Country::where('name', 'India')->first();
        $this->assertNotNull($country);
        $state = $country->states()->where('name', 'Telangana')->first();
        $this->assertNotNull($state);
        $this->assertTrue($state->cities()->where('name', 'Hyderabad')->exists());
    }

    /**
     * A value an admin deleted still occupies the unique index on these tables, so
     * re-adding it has to restore rather than insert beside it.
     */
    public function test_a_deleted_entry_is_restored_rather_than_colliding_with_its_own_unique_index(): void
    {
        $retired = ProjectType::create(['name' => 'Sky Mansions', 'is_active' => true, 'sort_order' => 99]);
        $retired->delete();

        $this->convert($this->superAdmin(), $this->seedRecord($this->registration(14, [
            'project_details' => ['project_type' => 'Sky Mansions'],
        ])))->assertSessionHasNoErrors();

        $this->assertSame(1, ProjectType::where('name', 'Sky Mansions')->count());
        $this->assertFalse(ProjectType::where('name', 'Sky Mansions')->first()->is_active);
    }

    public function test_the_project_block_is_mapped_onto_the_listing_and_its_child_tables(): void
    {
        $this->convert($this->superAdmin(), $this->seedRecord($this->registration(6)));

        $property = Property::with(['detail', 'unitTypes', 'media'])->firstOrFail();

        // Free text the vendor types, normalised into what our columns accept.
        $this->assertSame('Residential', $property->project_type, 'matched case-insensitively against the configured types');
        $this->assertSame('Under Construction', $property->project_status, '"ongoing" is one of ours under another name');
        $this->assertSame('West', $property->zone);
        $this->assertSame(12_000_000, $property->price_min, '"₹ 1.2 Cr" is a price, not a string');
        $this->assertSame(480, $property->total_units);
        $this->assertSame('12.50', $property->land_parcel_acres);
        $this->assertSame('2027-12-01', $property->possession_date->toDateString());

        $this->assertSame(['Metro 800 m', 'ORR 2 km'], $property->detail->connectivity_highlights);
        $this->assertSame(['Clubhouse', 'Gym', 'Swimming Pool'], $property->detail->amenities);
        $this->assertSame('3.50', $property->detail->cp_commission_percent);

        // Our spelling, not the vendor's: "3 BHK" is the "3BHK" already in Settings.
        $this->assertSame(['3BHK', '4BHK'], $property->unitTypes->pluck('label')->all());
        $this->assertSame(1850, $property->unitTypes->first()->super_built_up_area_sqft, 'the thousands comma is not a decimal point');
        $this->assertSame(12_000_000, $property->unitTypes->first()->price_min);

        // Hero image becomes the cover; everything else becomes a media row of its kind.
        $this->assertNotNull($property->cover_image_path);
        $this->assertSame(2, $property->media->where('kind', 'image')->count());
        $this->assertSame(1, $property->media->where('kind', 'brochure')->count());
    }

    /**
     * The vendor serves full-resolution originals — one registration is 97 MB across 13
     * files, a 15 MB hero among them. An admin's own upload is re-encoded in the browser
     * before it is sent, and an import has no browser, so this is the only thing standing
     * between those originals and the bucket.
     */
    public function test_an_imported_photo_is_compressed_the_way_an_uploaded_one_is(): void
    {
        $this->assetHostFaked = true;
        $original = $this->hugeJpeg();

        Http::fake(['*' => fn () => Http::response($original, 200, ['Content-Type' => 'image/jpeg'])]);

        $this->convert($this->superAdmin(), $this->seedRecord($this->registration(15)));

        $stored = Property::firstOrFail()->cover_image_path;
        $this->assertNotNull($stored);

        $bytes = Storage::disk('uploads')->get($stored);
        $this->assertLessThan(strlen($original), strlen($bytes), 'the stored copy must be smaller than the source');

        [$width, $height] = getimagesizefromstring($bytes);
        $this->assertLessThanOrEqual(ImageCompressor::MAX_EDGE, max($width, $height));
        $this->assertSame('jpg', pathinfo($stored, PATHINFO_EXTENSION), 're-encoded as JPEG, like the browser does');
    }

    /** A 3000px photo of noise — noise so it cannot be compressed away to nothing. */
    private function hugeJpeg(): string
    {
        $image = imagecreatetruecolor(3000, 2000);

        for ($x = 0; $x < 3000; $x += 3) {
            for ($y = 0; $y < 2000; $y += 3) {
                imagefilledrectangle($image, $x, $y, $x + 2, $y + 2, imagecolorallocate($image, rand(0, 255), rand(0, 255), rand(0, 255)));
            }
        }

        ob_start();
        imagejpeg($image, null, 100);
        imagedestroy($image);

        return (string) ob_get_clean();
    }

    public function test_a_failed_asset_download_costs_the_photo_and_not_the_import(): void
    {
        $this->fakeAssetHost(500);

        $this->convert($this->superAdmin(), $this->seedRecord($this->registration(8)))
            ->assertSessionHasNoErrors();

        $property = Property::firstOrFail();
        $this->assertNull($property->cover_image_path);
        $this->assertSame(0, $property->media()->count());
        $this->assertSame('Treasure Trove', $property->name, 'the listing itself is unaffected');
    }
}
