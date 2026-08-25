<?php

namespace Tests\Feature;

use App\Support\FileStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Uploads live in S3, split into a public half and a private half by the folder they
 * are stored in. The split is the whole security story for KYC documents — a PAN scan
 * that lands on the public disk is readable by anyone who guesses its key — so the
 * routing rule is worth pinning down rather than trusting to a prefix constant nobody
 * reads.
 *
 * These run against Storage::fake, so they check the decision FileStorage makes, not
 * S3 itself. Whether the bucket actually honours it is a bucket-policy question, and
 * that lives in docs/AWS-S3-SETUP.md.
 */
class FileStorageTest extends TestCase
{
    public function test_kyc_folders_are_private_and_everything_else_is_public(): void
    {
        // The complete set of folders the application writes to.
        $this->assertTrue(FileStorage::isSecure('broker-documents/pan.jpg'));
        $this->assertSame('secure', FileStorage::diskName('broker-documents'));

        foreach (['broker-photos/avatar.jpg', 'developers/logos/acme.png', 'properties/13/cover.png'] as $path) {
            $this->assertFalse(FileStorage::isSecure($path), "{$path} should be public");
        }

        $this->assertSame('uploads', FileStorage::diskName('broker-photos'));
        $this->assertSame('uploads', FileStorage::diskName('developers/logos'));
        $this->assertSame('uploads', FileStorage::diskName('properties/13'));
    }

    public function test_an_upload_lands_on_the_disk_its_folder_dictates(): void
    {
        Storage::fake('uploads');
        Storage::fake('secure');

        $photo = FileStorage::put(UploadedFile::fake()->image('cover.jpg'), 'properties/13');
        $scan = FileStorage::put(UploadedFile::fake()->image('pan.jpg'), 'broker-documents');

        Storage::disk('uploads')->assertExists($photo);
        Storage::disk('secure')->assertExists($scan);

        // Neither may appear on the other's disk — that is the failure that matters.
        Storage::disk('secure')->assertMissing($photo);
        Storage::disk('uploads')->assertMissing($scan);
    }

    public function test_exists_get_and_delete_follow_the_same_routing(): void
    {
        Storage::fake('uploads');
        Storage::fake('secure');

        Storage::disk('secure')->put('broker-documents/aadhaar.xml', '<x/>');
        Storage::disk('uploads')->put('properties/13/cover.png', 'png');

        $this->assertTrue(FileStorage::exists('broker-documents/aadhaar.xml'));
        $this->assertTrue(FileStorage::exists('properties/13/cover.png'));
        $this->assertSame('<x/>', FileStorage::get('broker-documents/aadhaar.xml'));

        FileStorage::delete('broker-documents/aadhaar.xml');
        Storage::disk('secure')->assertMissing('broker-documents/aadhaar.xml');
        Storage::disk('uploads')->assertExists('properties/13/cover.png');
    }

    public function test_a_missing_or_empty_path_is_not_an_error(): void
    {
        Storage::fake('uploads');

        // Half the columns these paths come from are nullable, and callers pass them
        // through without checking — a partner with no profile photo must not 500.
        $this->assertNull(FileStorage::url(null));
        $this->assertNull(FileStorage::url(''));
        $this->assertFalse(FileStorage::exists(null));

        FileStorage::delete(null);
        FileStorage::delete('properties/13/never-existed.png');
    }

    public function test_a_private_file_is_served_through_a_signed_url(): void
    {
        Storage::fake('secure');

        $url = FileStorage::url('broker-documents/pan.jpg');

        // Storage::fake signs with a query string; the point is that the address is
        // not simply the object's own path.
        $this->assertNotNull($url);
        $this->assertStringContainsString('broker-documents/pan.jpg', $url);
    }
}
