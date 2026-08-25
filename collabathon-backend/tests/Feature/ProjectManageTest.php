<?php

namespace Tests\Feature;

use App\Models\Developer;
use App\Models\Lead;
use App\Models\Property;
use App\Models\PropertyDetail;
use App\Models\PropertyMedia;
use App\Models\PropertyUnitType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Detail page, full edit, and delete for a project. */
class ProjectManageTest extends TestCase
{
    use RefreshDatabase;

    protected function superAdmin(): User
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

    protected function developer(string $name = 'Skyline Realty Group'): Developer
    {
        return Developer::create([
            'company_name' => $name,
            'contact_person' => 'A. Rahman',
            'email' => strtolower(str_replace(' ', '', $name)) . '@test.test',
            'city' => 'Dubai',
            'cp_payout_percent' => 2.5,
            'status' => 'active',
        ]);
    }

    /** A fully populated project, built directly so the tests do not depend on the form. */
    protected function project(Developer $developer): Property
    {
        $property = Property::create([
            'developer_id' => $developer->id,
            'name' => 'Azure Bay Residences',
            'slug' => 'azure-bay-residences-abcde',
            'project_type' => 'Residential',
            'project_status' => 'Under Construction',
            'listing_status' => 'active',
            'tagline' => 'Waterfront living',
            'description' => 'An overview.',
            'rera_number' => 'RERA-DXB-1',
            'rera_registered_at' => '2025-01-15',
            'rera_valid_till' => '2027-01-15',
            'state' => 'Dubai',
            'city' => 'Dubai',
            'locality' => 'Dubai Marina',
            'zone' => 'Central',
            'price_min' => 1_800_000,
            'price_max' => 3_200_000,
            'currency' => 'AED',
            'total_units' => 480,
            'towers' => 3,
            'land_parcel_acres' => 12.5,
            'open_space_percent' => 65,
            'launch_date' => '2025-03-01',
            'possession_date' => '2027-12-31',
            'construction_progress' => 35,
            'green_certification' => 'LEED Gold',
            'vastu_compliant' => true,
        ]);

        PropertyDetail::create([
            'property_id' => $property->id,
            'parking_details' => '2 covered bays',
            'amenities' => ['Clubhouse', 'Gym', 'Padel Court'],
            'payment_plan_options' => ['Construction-linked'],
            'bank_approvals' => ['Emirates NBD', 'ADCB'],
            'connectivity_highlights' => ['Metro — 800 m'],
            'awards' => ['Best Project 2025'],
            'cp_commission_percent' => 2.75,
            'sales_contact_name' => 'Nadia K.',
            'legal_due_diligence_path' => 'properties/1/legal.pdf',
        ]);

        PropertyUnitType::create([
            'property_id' => $property->id,
            'label' => '2BHK',
            'carpet_area_sqft' => 980,
            'price_min' => 1_800_000,
            'price_max' => 2_100_000,
            'units_count' => 240,
            'floor_plan_path' => 'properties/1/plan.pdf',
            'sort_order' => 0,
        ]);

        foreach ([['image', 'a.jpg'], ['image', 'b.jpg'], ['brochure', 'b.pdf']] as [$kind, $file]) {
            PropertyMedia::create([
                'property_id' => $property->id,
                'kind' => $kind,
                'path' => "properties/{$property->id}/{$file}",
            ]);
        }

        PropertyMedia::create([
            'property_id' => $property->id,
            'kind' => 'video',
            'url' => 'https://youtube.com/watch?v=x',
        ]);

        return $property;
    }

    public function test_the_detail_page_shows_every_group_of_the_sheet(): void
    {
        $property = $this->project($this->developer());

        $response = $this->actingAs($this->superAdmin())->get(route('admin.properties.show', $property));

        $response->assertOk();

        foreach (['Project basics', 'Location', 'Configuration &amp; pricing', 'Specifications',
                  'Timeline &amp; legal', 'Commercial terms', 'Contact &amp; sales',
                  'Compliance &amp; trust', 'Unit types', 'Gallery', 'Amenities', 'Attachments'] as $group) {
            $response->assertSee($group, false);
        }

        // Values from all four tables reach the page.
        $response->assertSee('Azure Bay Residences');
        $response->assertSee('Skyline Realty Group');
        $response->assertSee('RERA-DXB-1');
        $response->assertSee('2 covered bays');
        $response->assertSee('Padel Court');
        $response->assertSee('Emirates NBD');
        $response->assertSee('2BHK');
        // sq.m. is derived from sq.ft. rather than stored — 980 sq.ft. ≈ 91.0 m².
        $response->assertSee('91.0 m²', false);

        // Edit and delete are reachable from here.
        $response->assertSee(route('admin.properties.edit', $property), false);
        $response->assertSee(route('admin.properties.destroy', $property), false);
    }

    public function test_the_edit_form_is_prefilled_from_all_four_tables(): void
    {
        $property = $this->project($this->developer());

        $response = $this->actingAs($this->superAdmin())->get(route('admin.properties.edit', $property));

        $response->assertOk();

        // Scalars off `properties`.
        $response->assertSee('value="Azure Bay Residences"', false);
        $response->assertSee('value="Dubai Marina"', false);
        // A date cast must reach <input type="date"> as Y-m-d, not "Y-m-d H:i:s".
        $response->assertSee('value="2027-12-31"', false);
        // Long-tail text off `property_details`.
        $response->assertSee('value="2 covered bays"', false);
        // A JSON list column, rendered back as newline-per-item textarea content.
        $response->assertSee("Emirates NBD\nADCB", false);
        // Amenities split back into known checkboxes plus free-typed extras.
        $response->assertSee('value="Padel Court"', false);
        // A unit-type row is seeded, carrying its stored floor plan so the rebuild on save
        // does not drop it. The seed is JSON inside an HTML attribute, so the path arrives
        // double-escaped — assert on the filename and the carrying field, not the raw path.
        $response->assertSee('2BHK', false);
        $response->assertSee('existing_floor_plan', false);
        $response->assertSee('plan.pdf', false);
        // The external link lives in property_media, not on a column.
        $response->assertSee('value="https://youtube.com/watch?v=x"', false);
    }

    public function test_a_full_edit_updates_every_table_and_keeps_unreplaced_attachments(): void
    {
        Storage::fake('uploads');
        $developer = $this->developer();
        $other = $this->developer('Meridian Estates');
        $property = $this->project($developer);

        $this->actingAs($this->superAdmin())
            ->patch(route('admin.properties.update', $property), [
                '_full' => '1',
                'developer_id' => $other->id,
                'name' => 'Azure Bay Residences Phase 2',
                'project_type' => 'Mixed-use',
                'project_status' => 'Ready to Move',
                'listing_status' => 'active',
                'city' => 'Abu Dhabi',
                'currency' => 'AED',
                'price_min' => 2_000_000,
                'price_max' => 4_000_000,
                'parking_details' => '3 covered bays',
                'bank_approvals' => "Mashreq\nFAB",
                'amenities' => ['Gym', 'Swimming Pool'],
                'amenities_extra' => 'Sky Lounge',
                'possession_date' => '2028-06-30',
                'unit_types' => [[
                    'label' => '3BHK',
                    'carpet_area_sqft' => 1400,
                    // Keeps the stored plan instead of re-uploading it.
                    'existing_floor_plan' => 'properties/1/plan.pdf',
                ]],
            ])
            ->assertRedirect(route('admin.properties.show', $property))
            ->assertSessionHasNoErrors();

        $property->refresh()->load(['detail', 'unitTypes', 'media']);

        $this->assertSame('Azure Bay Residences Phase 2', $property->name);
        $this->assertSame($other->id, $property->developer_id);
        $this->assertSame('Mixed-use', $property->project_type);
        $this->assertSame('Abu Dhabi', $property->city);
        $this->assertSame('2028-06-30', $property->possession_date->toDateString());

        // Renaming must not change the slug — links already shared with brokers keep working.
        $this->assertSame('azure-bay-residences-abcde', $property->slug);

        $this->assertSame('3 covered bays', $property->detail->parking_details);
        $this->assertSame(['Mashreq', 'FAB'], $property->detail->bank_approvals);
        $this->assertSame(['Gym', 'Swimming Pool', 'Sky Lounge'], $property->detail->amenities);
        // No new document uploaded, so the stored path survives.
        $this->assertSame('properties/1/legal.pdf', $property->detail->legal_due_diligence_path);

        // Unit types are rebuilt, and the carried floor plan is preserved.
        $this->assertCount(1, $property->unitTypes);
        $this->assertSame('3BHK', $property->unitTypes->first()->label);
        $this->assertSame('properties/1/plan.pdf', $property->unitTypes->first()->floor_plan_path);

        // Attachments nobody touched are still attached.
        $this->assertCount(2, $property->media->where('kind', 'image'));
        $this->assertCount(1, $property->media->where('kind', 'brochure'));
    }

    public function test_ticked_attachments_are_removed_and_their_files_deleted(): void
    {
        Storage::fake('uploads');
        $property = $this->project($this->developer());

        $image = $property->media()->where('kind', 'image')->first();
        Storage::disk('uploads')->put($image->path, 'x');

        $this->actingAs($this->superAdmin())
            ->patch(route('admin.properties.update', $property), [
                '_full' => '1',
                'developer_id' => $property->developer_id,
                'name' => $property->name,
                'project_type' => 'Residential',
                'project_status' => 'New Launch',
                'listing_status' => 'active',
                'city' => 'Dubai',
                'currency' => 'AED',
                'price_min' => 1_800_000,
                'price_max' => 3_200_000,
                'remove_media' => [$image->id],
            ])
            ->assertSessionHasNoErrors();

        $this->assertNull(PropertyMedia::find($image->id));
        Storage::disk('uploads')->assertMissing($image->path);
        // The other image is untouched.
        $this->assertCount(1, $property->fresh()->media->where('kind', 'image'));
    }

    /**
     * The row menu's publish/draft/archive item posts `listing_status` alone. Without the
     * `_full` guard in update(), that single field would rebuild the child records and wipe
     * the detail row, the unit types and every attachment.
     */
    public function test_the_quick_status_action_does_not_touch_child_records(): void
    {
        $property = $this->project($this->developer());

        $this->actingAs($this->superAdmin())
            ->patch(route('admin.properties.update', $property), ['listing_status' => 'archived'])
            ->assertSessionHasNoErrors();

        $property->refresh()->load(['detail', 'unitTypes', 'media']);

        $this->assertSame('archived', $property->listing_status);
        $this->assertSame('Azure Bay Residences', $property->name);
        $this->assertSame('2 covered bays', $property->detail->parking_details);
        $this->assertCount(1, $property->unitTypes);
        $this->assertCount(4, $property->media);
    }

    public function test_delete_hides_the_project_but_keeps_its_leads(): void
    {
        $developer = $this->developer();
        $property = $this->project($developer);

        $broker = User::create([
            'name' => 'Broker', 'email' => 'broker@test.test', 'password' => 'password',
            'role' => User::ROLE_BROKER, 'status' => User::STATUS_ACTIVE,
        ]);
        $lead = Lead::create([
            'property_id' => $property->id,
            'broker_id' => $broker->id,
            'developer_id' => $developer->id,
            'client_name' => 'A Client',
            'client_mobile' => '+971500000000',
        ]);

        $this->actingAs($this->superAdmin())
            ->delete(route('admin.properties.destroy', $property))
            ->assertRedirect(route('admin.properties'))
            ->assertSessionHasNoErrors();

        // Gone from every list and from broker view…
        $this->assertSame(0, Property::count());
        $this->assertNotNull($property->fresh()?->deleted_at ?? Property::withTrashed()->find($property->id)->deleted_at);
        // …but the lead history against it survives, which a hard delete would cascade away.
        $this->assertNotNull(Lead::find($lead->id));
    }

    public function test_the_projects_list_links_each_row_to_its_detail_page(): void
    {
        $property = $this->project($this->developer());

        $this->actingAs($this->superAdmin())
            ->get(route('admin.properties'))
            ->assertOk()
            ->assertSee(route('admin.properties.show', $property), false)
            ->assertSee(route('admin.properties.edit', $property), false);
    }
}
