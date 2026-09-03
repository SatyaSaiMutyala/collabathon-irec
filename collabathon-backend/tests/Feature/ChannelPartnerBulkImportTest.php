<?php

namespace Tests\Feature;

use App\Models\ApprovalDecision;
use App\Models\BrokerProfile;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * The seams a spreadsheet import gets wrong: the multi-value cells that become JSON
 * arrays, a mobile number typed three different ways, and one bad row taking the other
 * rows down with it.
 */
class ChannelPartnerBulkImportTest extends TestCase
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

    /** The template's own header row, so the fixtures below can't drift from it. */
    private function header(): string
    {
        return 'name,email,mobile,alternate_mobile,is_company,company_name,city,state,zones,segments,'
            . 'rera_number,years_of_experience,team_size,'
            . 'pan_card,aadhaar_card,gst_number,residence_address,office_address,company_website,'
            . "instagram,facebook,youtube,twitter,linkedin,project_contributions,password\n";
    }

    private function upload(string $csv): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('partners.csv', $csv);
    }

    public function test_the_roster_page_offers_the_upload_and_the_template(): void
    {
        $this->actingAs($this->superAdmin())
            ->get('/admin/cp')
            ->assertOk()
            ->assertSee('Bulk upload')
            ->assertSee(route('admin.cp.bulk-import.template'));
    }

    public function test_the_template_lists_the_columns_the_import_reads(): void
    {
        $response = $this->actingAs($this->superAdmin())
            ->get('/admin/cp/bulk-import/template');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertSame($this->header(), strtok($response->getContent(), "\n") . "\n");
    }

    public function test_a_row_becomes_an_active_partner_with_a_profile_and_an_audit_trail(): void
    {
        $admin = $this->superAdmin();

        $csv = $this->header()
            . 'Rahul Verma,rahul@verma.test,+91 90000 00000,,yes,Verma Properties,Hyderabad,Telangana,'
            . 'Kokapet|Gachibowli,Residential|Commercial,RERA/TEL/AGT/1,6,4,'
            . ",,,,,,,,,,,,\n";

        $this->actingAs($admin)
            ->post('/admin/cp/bulk-import', ['file' => $this->upload($csv)])
            ->assertOk()
            ->assertSee('Rahul Verma');

        $partner = User::where('email', 'rahul@verma.test')->firstOrFail();

        $this->assertSame(User::ROLE_BROKER, $partner->role);
        // Active, not pending: an uploaded roster is already vetted — see bulkImport().
        $this->assertSame(User::STATUS_ACTIVE, $partner->status);
        // The +91 and the spaces are stripped, because OTP sign-in matches 10 exact digits.
        $this->assertSame('9000000000', $partner->mobile);

        $profile = BrokerProfile::where('user_id', $partner->id)->firstOrFail();

        $this->assertTrue($profile->is_company);
        $this->assertSame('Verma Properties', $profile->company_name);
        $this->assertSame(['Kokapet', 'Gachibowli'], $profile->zones);
        $this->assertSame(['Residential', 'Commercial'], $profile->segments);
        $this->assertSame(6, $profile->years_of_experience);
        // The partner never signed the declaration — the admin uploaded the sheet.
        $this->assertFalse($profile->confirm_accuracy);

        $decision = ApprovalDecision::where('user_id', $partner->id)->firstOrFail();

        $this->assertSame('approved', $decision->decision);
        $this->assertSame($admin->id, $decision->decided_by);
    }

    public function test_a_bad_row_fails_on_its_own_without_blocking_the_rest(): void
    {
        $csv = $this->header()
            . "Missing Mobile,nomobile@verma.test,,,,,,,,,,,,,,,,,,,,,,,,\n"
            . 'Good Row,good@verma.test,9000000001,,no,,Pune,Maharashtra,,Residential,'
            . ",,,,,,,,,,,,,,,,\n";

        $this->actingAs($this->superAdmin())
            ->post('/admin/cp/bulk-import', ['file' => $this->upload($csv)])
            ->assertOk()
            ->assertSee('Missing Mobile')
            ->assertSee('Failed');

        $this->assertDatabaseMissing('users', ['email' => 'nomobile@verma.test']);
        $this->assertDatabaseHas('users', ['email' => 'good@verma.test', 'status' => User::STATUS_ACTIVE]);
    }

    public function test_a_row_with_only_the_required_columns_filled_in_is_accepted(): void
    {
        // Every other cell blank — the point of the optional columns is that they can be
        // left empty, not that they have to be deleted from the sheet.
        $csv = $this->header()
            . "Solo Agent,solo@verma.test,9000000004,,,,,,,,,,,,,,,,,,,,,,,,\n";

        $this->actingAs($this->superAdmin())
            ->post('/admin/cp/bulk-import', ['file' => $this->upload($csv)])
            ->assertOk()
            ->assertSee('Created');

        $partner = User::where('email', 'solo@verma.test')->firstOrFail();
        $profile = BrokerProfile::where('user_id', $partner->id)->firstOrFail();

        $this->assertFalse($profile->is_company);
        $this->assertNull($profile->company_name);
        $this->assertNull($profile->city);
        $this->assertSame([], $profile->zones);
        $this->assertSame([], $profile->segments);
    }

    public function test_the_wrong_sheet_fails_once_instead_of_on_every_row(): void
    {
        // The developers template — right idea, wrong file.
        $csv = "company_name,contact_person,email,mobile,city\nSkyline,A. Rahman,a@skyline.test,9000000005,Dubai\n";

        $this->actingAs($this->superAdmin())
            ->post('/admin/cp/bulk-import', ['file' => $this->upload($csv)])
            ->assertRedirect()
            ->assertSessionHasErrors('file');

        // The developers sheet carries email/mobile under those exact names, so `name` —
        // its contact column is `contact_person` — is the one that turns up missing.
        $this->assertStringContainsString(
            'missing the name column',
            session('errors')->first('file'),
        );
        $this->assertDatabaseMissing('users', ['email' => 'a@skyline.test']);
    }

    public function test_a_sheet_with_no_data_rows_says_so(): void
    {
        $this->actingAs($this->superAdmin())
            ->post('/admin/cp/bulk-import', ['file' => $this->upload($this->header())])
            ->assertRedirect()
            ->assertSessionHasErrors('file');

        $this->assertStringContainsString('no data rows', session('errors')->first('file'));
    }

    public function test_an_unreadable_is_company_cell_fails_the_row_instead_of_guessing(): void
    {
        $csv = $this->header()
            . "Maybe Co,maybe@verma.test,9000000006,,maybe,,,,,,,,,,,,,,,,,,,,,,\n";

        $this->actingAs($this->superAdmin())
            ->post('/admin/cp/bulk-import', ['file' => $this->upload($csv)])
            ->assertOk()
            ->assertSee('is_company column must be yes or no');

        $this->assertDatabaseMissing('users', ['email' => 'maybe@verma.test']);
    }

    public function test_a_duplicate_mobile_is_rejected_rather_than_shadowing_an_existing_sign_in(): void
    {
        User::create([
            'name' => 'Existing Partner',
            'email' => 'existing@verma.test',
            'password' => 'password',
            'mobile' => '9000000002',
            'role' => User::ROLE_BROKER,
            'status' => User::STATUS_ACTIVE,
        ]);

        $csv = $this->header()
            . "Clashing Partner,clash@verma.test,9000000002,,,,,,,,,,,,,,,,,,,,,,,,\n";

        $this->actingAs($this->superAdmin())
            ->post('/admin/cp/bulk-import', ['file' => $this->upload($csv)])
            ->assertOk()
            ->assertSee('mobile number');

        $this->assertDatabaseMissing('users', ['email' => 'clash@verma.test']);
    }

    public function test_a_role_without_edit_rights_on_the_module_cannot_import(): void
    {
        $role = Role::create(['name' => 'Viewer', 'is_system' => false]);
        $role->permissions()->create([
            'module' => 'cp', 'can_view' => true, 'can_edit' => false, 'can_delete' => false,
        ]);

        $viewer = User::create([
            'name' => 'Viewer',
            'email' => 'viewer@example.test',
            'password' => 'password',
            'role' => User::ROLE_ADMIN,
            'role_id' => $role->id,
            'status' => User::STATUS_ACTIVE,
        ]);

        $csv = $this->header()
            . "Blocked,blocked@verma.test,9000000003,,,,,,,,,,,,,,,,,,,,,,,,\n";

        $this->actingAs($viewer)
            ->post('/admin/cp/bulk-import', ['file' => $this->upload($csv)])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'blocked@verma.test']);
    }
}
