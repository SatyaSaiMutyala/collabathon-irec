<?php

namespace Tests\Feature;

use App\Models\BrokerProfile;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The registration form makes PAN, Aadhaar and the RERA certificate required attachments,
 * but the endpoint accepted only the passport photo — so every scan was discarded and the
 * admin's Documents panel read "Not provided" on every registration.
 */
class BrokerRegistrationDocumentsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'B. Broker',
            'email' => 'broker@example.test',
            'password' => 'password123',
            'mobile' => '+971 50 111 2222',
            'rera_number' => 'RERA-BRK-1',
            'pan_card' => 'ABCDE1234F',
            'aadhaar_card' => '1234 5678 9012',
            'city' => 'Dubai',
            'confirm_accuracy' => '1',
        ], $overrides);
    }

    public function test_every_uploaded_document_is_stored_against_the_profile(): void
    {
        Storage::fake('public');

        $this->postJson('/api/v1/auth/register', $this->payload([
            'photo' => UploadedFile::fake()->image('me.jpg'),
            'pan_card_file' => UploadedFile::fake()->image('pan.jpg'),
            'aadhaar_file' => UploadedFile::fake()->image('aadhaar.png'),
            'rera_certificate_file' => UploadedFile::fake()->create('rera.pdf', 200, 'application/pdf'),
            'gst_file' => UploadedFile::fake()->image('gst.jpg'),
            'cheque_file' => UploadedFile::fake()->image('cheque.jpg'),
            'signature_file' => UploadedFile::fake()->image('signature.png'),
        ]))->assertCreated();

        $profile = BrokerProfile::firstOrFail();

        foreach ([
            'photo_path', 'pan_card_path', 'aadhaar_path',
            'rera_certificate_path', 'gst_path', 'cheque_path', 'signature_path',
        ] as $column) {
            $this->assertNotNull($profile->{$column}, "[{$column}] was not stored.");
            Storage::disk('public')->assertExists($profile->{$column});
        }

        // Scans are grouped away from the profile photo so a KYC purge can target them.
        $this->assertStringStartsWith('broker-documents/', $profile->pan_card_path);
        $this->assertStringStartsWith('broker-photos/', $profile->photo_path);
    }

    public function test_documents_are_optional_and_absent_ones_stay_null(): void
    {
        Storage::fake('public');

        $this->postJson('/api/v1/auth/register', $this->payload([
            'pan_card_file' => UploadedFile::fake()->image('pan.jpg'),
        ]))->assertCreated();

        $profile = BrokerProfile::firstOrFail();

        $this->assertNotNull($profile->pan_card_path);
        $this->assertNull($profile->aadhaar_path);
        $this->assertNull($profile->photo_path);
    }

    public function test_an_oversized_or_wrong_type_document_is_rejected(): void
    {
        Storage::fake('public');

        $this->postJson('/api/v1/auth/register', $this->payload([
            'pan_card_file' => UploadedFile::fake()->create('malware.exe', 10, 'application/x-msdownload'),
        ]))->assertStatus(422)->assertJsonValidationErrors('pan_card_file');

        $this->assertSame(0, BrokerProfile::count());
    }

    /** The whole point: what was uploaded has to reach the admin's Documents panel. */
    public function test_the_admin_documents_panel_links_the_uploaded_scans(): void
    {
        Storage::fake('public');

        $this->postJson('/api/v1/auth/register', $this->payload([
            'pan_card_file' => UploadedFile::fake()->image('pan.jpg'),
            'rera_certificate_file' => UploadedFile::fake()->create('rera.pdf', 120, 'application/pdf'),
        ]))->assertCreated();

        $role = Role::create(['name' => 'Super Admin', 'is_system' => true]);
        $admin = User::create([
            'name' => 'Ops', 'email' => 'ops@example.test', 'password' => 'password',
            'role' => User::ROLE_ADMIN, 'role_id' => $role->id,
            'status' => User::STATUS_ACTIVE, 'email_verified_at' => now(),
        ]);

        $broker = User::where('role', User::ROLE_BROKER)->firstOrFail();
        $profile = $broker->brokerProfile;

        $response = $this->actingAs($admin)->get(route('admin.approvals.show', $broker));

        $response->assertOk();
        $response->assertSee(Storage::disk('public')->url($profile->pan_card_path), false);
        $response->assertSee(Storage::disk('public')->url($profile->rera_certificate_path), false);
        // Two of six attached, so the counter must not still read zero.
        $response->assertSee('2 of 6');
    }
}
