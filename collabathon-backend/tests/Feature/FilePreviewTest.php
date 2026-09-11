<?php

namespace Tests\Feature;

use App\Models\Developer;
use App\Models\Property;
use App\Models\PropertyDetail;
use App\Models\PropertyMedia;
use App\Models\PropertyUnitType;
use App\Models\Role;
use App\Models\User;
use App\Support\FilePreview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Uploaded files open in a preview overlay rather than a new browser tab.
 *
 * Two things are worth holding still here. First, the kind detection: it decides whether a
 * file is shown as an image, handed to the browser's PDF viewer, played, or offered as a
 * download, and it has to survive a signed S3 URL where the extension sits in front of a
 * query string. Second, the wiring: every link that used to be a bare `target="_blank"`
 * anchor now carries the payload the overlay reads, and a page missing one of those quietly
 * loses its preview with nothing else looking wrong.
 */
class FilePreviewTest extends TestCase
{
    use RefreshDatabase;

    private ?User $admin = null;

    /**
     * Memoised: several tests below load two pages in one run, and a second call would
     * trip the unique constraint on the role name rather than hand back the same admin.
     */
    private function superAdmin(): User
    {
        if ($this->admin) {
            return $this->admin;
        }

        $role = Role::create(['name' => 'Super Admin', 'is_system' => true]);

        return $this->admin = User::create([
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
            'city' => 'Hyderabad',
            'cp_payout_percent' => 2.5,
            'status' => 'active',
            'logo_path' => 'developers/logos/skyline.png',
        ]);
    }

    /** A project carrying one of every attachment shape the two pages can render. */
    private function project(Developer $developer): Property
    {
        $property = Property::create([
            'developer_id' => $developer->id,
            'name' => 'Azure Bay Residences',
            'slug' => 'azure-bay-residences-abcde',
            'project_type' => 'Residential',
            'project_status' => 'Under Construction',
            'listing_status' => 'active',
            'state' => 'Telangana',
            'city' => 'Hyderabad',
            'currency' => 'INR',
            'cover_image_path' => 'properties/1/cover.jpg',
        ]);

        PropertyDetail::create([
            'property_id' => $property->id,
            'terms_type' => 'document',
            'terms_title' => 'Channel partner terms',
            'terms_document_path' => 'properties/1/terms.pdf',
        ]);

        PropertyUnitType::create([
            'property_id' => $property->id,
            'label' => '2BHK',
            'floor_plan_path' => 'properties/1/plan-2bhk.pdf',
            'sort_order' => 0,
        ]);

        foreach ([['image', 'gallery-a.jpg'], ['image', 'gallery-b.jpg'], ['brochure', 'brochure.pdf'],
            ['price_list', 'prices.pdf']] as [$kind, $file]) {
            PropertyMedia::create([
                'property_id' => $property->id,
                'kind' => $kind,
                'path' => "properties/{$property->id}/{$file}",
            ]);
        }

        // An external walkthrough: a link, not an upload. Nothing to preview, and the
        // Documents panel has to leave it out rather than render a chip pointing at null.
        PropertyMedia::create([
            'property_id' => $property->id,
            'kind' => 'virtual_tour',
            'url' => 'https://my.matterport.com/show/?m=abc',
        ]);

        return $property;
    }

    // ------------------------------------------------------------------ kind detection

    public function test_each_extension_maps_to_the_viewer_that_can_show_it(): void
    {
        foreach (['photo.jpg', 'photo.JPEG', 'shot.png', 'anim.gif', 'modern.webp', 'logo.svg'] as $path) {
            $this->assertSame('image', FilePreview::kind($path), $path);
        }

        $this->assertSame('pdf', FilePreview::kind('brochure.pdf'));
        $this->assertSame('pdf', FilePreview::kind('BROCHURE.PDF'));
        $this->assertSame('video', FilePreview::kind('walkthrough.mp4'));
        $this->assertSame('video', FilePreview::kind('clip.webm'));
    }

    public function test_anything_a_browser_cannot_show_inline_falls_back_to_a_download(): void
    {
        foreach (['prices.xlsx', 'terms.docx', 'pack.zip', 'notes', '', null] as $path) {
            $this->assertSame('file', FilePreview::kind($path), var_export($path, true));
            $this->assertFalse(FilePreview::isViewable($path));
        }
    }

    public function test_a_signed_url_is_read_past_its_query_string(): void
    {
        // The exact shape FileStorage::temporaryUrl() hands over for a private object. Read
        // naively, the extension comes back as "pdf?X-Amz-Algorithm=..." and every signed
        // document would have been offered as an undisplayable download.
        $signed = 'https://bucket.s3.ap-south-1.amazonaws.com/private/properties/1/brochure.pdf'
            .'?X-Amz-Algorithm=AWS4-HMAC-SHA256&X-Amz-Expires=900&X-Amz-Signature=deadbeef';

        $this->assertSame('pdf', FilePreview::kind($signed));
        $this->assertSame('image', FilePreview::kind('https://cdn.test/public/logo.png?v=2'));
    }

    // ------------------------------------------------------------------ the overlay itself

    public function test_the_overlay_is_mounted_once_for_every_admin_page(): void
    {
        $response = $this->actingAs($this->superAdmin())
            ->get(route('admin.developers'))
            ->assertOk();

        $response->assertSee('x-data="filePreview()"', false);
        // Mounted by the layout, so exactly one per page — a second copy would answer the
        // same window event twice and open two overlays on one click.
        $this->assertSame(1, substr_count($response->getContent(), 'x-data="filePreview()"'));
    }

    // ------------------------------------------------------------------ listing details page

    public function test_every_file_on_the_listing_page_opens_in_the_preview(): void
    {
        $property = $this->project($this->developer());

        $response = $this->actingAs($this->superAdmin())
            ->get(route('admin.properties.show', $property))
            ->assertOk();

        $html = $response->getContent();

        // Cover image, gallery, attachments, floor plan and the terms document — five call
        // sites, each of which was a plain new-tab link before.
        $this->assertStringContainsString('data-preview-group="gallery"', $html);
        $this->assertStringContainsString('data-preview-group="attachments"', $html);
        $this->assertStringContainsString('data-preview-group="floor-plans"', $html);

        // The kind travels with the link, worked out from the stored path.
        $this->assertStringContainsString('&quot;kind&quot;:&quot;pdf&quot;', $html);
        $this->assertStringContainsString('&quot;kind&quot;:&quot;image&quot;', $html);

        // Named, not left as a URL hash — the overlay header is the only label an admin
        // gets while looking at the file.
        $this->assertStringContainsString('Channel partner terms', $html);
        $this->assertStringContainsString('2BHK floor plan', $html);
    }

    public function test_both_gallery_images_join_the_same_set(): void
    {
        $property = $this->project($this->developer());

        $html = $this->actingAs($this->superAdmin())
            ->get(route('admin.properties.show', $property))
            ->assertOk()
            ->getContent();

        // Two images, two grouped links — this is what makes the overlay's arrows step
        // from one to the other instead of closing after the first.
        $this->assertSame(2, substr_count($html, 'data-preview-group="gallery"'));
    }

    public function test_an_external_walkthrough_link_is_left_as_a_link(): void
    {
        $property = $this->project($this->developer());

        $html = $this->actingAs($this->superAdmin())
            ->get(route('admin.properties.show', $property))
            ->assertOk()
            ->getContent();

        // A Matterport tour is someone else's page, not a file. It keeps its new-tab link,
        // and must never be handed to the overlay, which would show it as an
        // undisplayable download.
        $this->assertStringContainsString('https://my.matterport.com/show/?m=abc', $html);
        $this->assertStringNotContainsString('matterport.com/show/?m=abc&quot;,&quot;name', $html);
    }

    public function test_a_caption_full_of_quotes_and_markup_still_parses(): void
    {
        $developer = $this->developer();
        $property = $this->project($developer);

        // The payload is JSON inside an HTML attribute, so a caption carrying the very
        // characters both layers use is where it breaks. It fails silently too: the page
        // renders, the link looks right, and JSON.parse throws only when someone clicks.
        $caption = 'He said "great" & <b>bold</b> — 5\' 6" balcony';

        PropertyMedia::create([
            'property_id' => $property->id,
            'kind' => 'image',
            'path' => 'properties/1/tricky.jpg',
            'caption' => $caption,
        ]);

        $html = $this->actingAs($this->superAdmin())
            ->get(route('admin.properties.show', $property))
            ->assertOk()
            ->getContent();

        preg_match_all('/data-preview-item="([^"]*)"/', $html, $matches);
        $this->assertNotEmpty($matches[1]);

        $names = [];
        foreach ($matches[1] as $raw) {
            // Exactly what the browser hands to dataset, then to JSON.parse.
            $decoded = json_decode(html_entity_decode($raw, ENT_QUOTES, 'UTF-8'), true);

            $this->assertIsArray($decoded, 'Every preview payload must survive JSON.parse.');
            $this->assertArrayHasKey('url', $decoded);
            $names[] = $decoded['name'];
        }

        $this->assertContains($caption, $names);
    }

    // ------------------------------------------------------------------ developer details page

    public function test_the_developer_page_gathers_every_file_from_its_projects(): void
    {
        $developer = $this->developer();
        $this->project($developer);

        $response = $this->actingAs($this->superAdmin())
            ->get(route('admin.developers.show', $developer))
            ->assertOk();

        $html = $response->getContent();

        $response->assertSee('Project documents');
        // Cover + 2 gallery + brochure + price list + terms + floor plan = 7 files, all in
        // one set so the arrows walk the developer's whole paperwork.
        $this->assertSame(7, substr_count($html, 'data-preview-group="developer-documents"'));

        $response->assertSee('Cover image');
        $response->assertSee('Brochure');
        $response->assertSee('Price list');
        $response->assertSee('Channel partner terms');
        $response->assertSee('2BHK floor plan');
        // The project each file belongs to is named above its chips.
        $response->assertSee('Azure Bay Residences');
    }

    public function test_the_developer_logo_is_previewable_and_the_initials_fallback_is_not(): void
    {
        $developer = $this->developer();

        $this->actingAs($this->superAdmin())
            ->get(route('admin.developers.show', $developer))
            ->assertOk()
            ->assertSee('Skyline Realty Group logo', false);

        // No upload, no file: the initials placeholder must not pretend to be one.
        $developer->update(['logo_path' => null]);

        $this->actingAs($this->superAdmin())
            ->get(route('admin.developers.show', $developer))
            ->assertOk()
            ->assertDontSee('Skyline Realty Group logo', false);
    }

    public function test_a_developer_with_nothing_uploaded_says_so(): void
    {
        $developer = $this->developer();

        // A project with no attachments at all is dropped from the panel rather than
        // listed as an empty heading.
        Property::create([
            'developer_id' => $developer->id,
            'name' => 'Paperless Project',
            'slug' => 'paperless-project-abcde',
            'project_type' => 'Residential',
            'listing_status' => 'draft',
            'city' => 'Hyderabad',
            'currency' => 'INR',
        ]);

        $this->actingAs($this->superAdmin())
            ->get(route('admin.developers.show', $developer))
            ->assertOk()
            ->assertSee('No documents yet')
            ->assertDontSee('data-preview-group="developer-documents"', false);
    }
}
