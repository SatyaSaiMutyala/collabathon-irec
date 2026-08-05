<?php

namespace Tests\Feature;

use App\Models\ApprovalDecision;
use App\Models\BrokerProfile;
use App\Models\Developer;
use App\Models\Lead;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * Admin decisions must be correctable. Approving a broker by mistake, or accepting a lead
 * on the wrong project, used to be permanent — approve/reject was guarded to pending-only
 * registrations and a lead had no admin control at all.
 */
class ReversibleDecisionsTest extends TestCase
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

    /** Emails are unique per call so one test can hold brokers in several states at once. */
    private function broker(string $status = User::STATUS_PENDING): User
    {
        static $seq = 0;
        $seq++;

        $broker = User::create([
            'name' => 'B. Broker',
            'email' => "broker{$seq}@example.test",
            'password' => 'password',
            'role' => User::ROLE_BROKER,
            'status' => $status,
        ]);

        BrokerProfile::create([
            'user_id' => $broker->id,
            'company_name' => 'Broker & Co',
            'rera_number' => 'RERA-BRK-1',
            'city' => 'Dubai',
        ]);

        return $broker;
    }

    private function lead(): Lead
    {
        $developer = Developer::create([
            'company_name' => 'Skyline Realty Group',
            'contact_person' => 'A. Rahman',
            'email' => 'sales@skyline.test',
            'city' => 'Dubai',
            'cp_payout_percent' => 2.5,
            'status' => 'active',
        ]);

        $property = Property::create([
            'developer_id' => $developer->id,
            'name' => 'Azure Bay Residences',
            'slug' => 'azure-bay-abcde',
            'project_type' => 'Residential',
            'project_status' => 'New Launch',
            'listing_status' => 'active',
            'city' => 'Dubai',
            'price_min' => 1_800_000,
            'price_max' => 3_200_000,
        ]);

        return Lead::create([
            'property_id' => $property->id,
            'broker_id' => $this->broker(User::STATUS_ACTIVE)->id,
            'developer_id' => $developer->id,
            'status' => Lead::STATUS_VIEWED,
            'viewed_at' => now(),
        ]);
    }

    // ------------------------------------------------------------------ broker approvals

    public function test_an_approved_broker_can_have_access_revoked(): void
    {
        $admin = $this->superAdmin();
        $broker = $this->broker();

        $this->actingAs($admin)->post(route('admin.approvals.approve', $broker))->assertSessionHasNoErrors();
        $this->assertSame(User::STATUS_ACTIVE, $broker->fresh()->status);

        // A token exists exactly as it would after the broker signed in.
        $broker->fresh()->createToken('mobile');
        $this->assertSame(1, PersonalAccessToken::count());

        $this->actingAs($admin)
            ->post(route('admin.approvals.reject', $broker), ['reason' => 'RERA number could not be verified.'])
            ->assertSessionHasNoErrors();

        $this->assertSame(User::STATUS_REJECTED, $broker->fresh()->status);
        // Revoking has to bite immediately, not at the next login.
        $this->assertSame(0, PersonalAccessToken::count());
    }

    public function test_a_rejected_broker_can_be_re_approved(): void
    {
        $admin = $this->superAdmin();
        $broker = $this->broker();

        $this->actingAs($admin)
            ->post(route('admin.approvals.reject', $broker), ['reason' => 'Missing RERA certificate.'])
            ->assertSessionHasNoErrors();
        $this->assertSame(User::STATUS_REJECTED, $broker->fresh()->status);

        $this->actingAs($admin)->post(route('admin.approvals.approve', $broker))->assertSessionHasNoErrors();
        $this->assertSame(User::STATUS_ACTIVE, $broker->fresh()->status);
    }

    /** The audit table is append-only, so reversing keeps the earlier reason on the record. */
    public function test_every_decision_is_appended_rather_than_overwritten(): void
    {
        $admin = $this->superAdmin();
        $broker = $this->broker();

        $this->actingAs($admin)->post(route('admin.approvals.approve', $broker));
        $this->actingAs($admin)->post(route('admin.approvals.reject', $broker), ['reason' => 'Licence expired.']);
        $this->actingAs($admin)->post(route('admin.approvals.approve', $broker), ['internal_note' => 'Renewed licence seen.']);

        $history = ApprovalDecision::where('user_id', $broker->id)->orderBy('id')->get();

        $this->assertCount(3, $history);
        $this->assertSame(['approved', 'rejected', 'approved'], $history->pluck('decision')->all());
        $this->assertSame('Licence expired.', $history[1]->reason);
        $this->assertSame('Renewed licence seen.', $history[2]->internal_note);
        $this->assertSame($admin->id, $history[2]->decided_by);
    }

    public function test_a_rejection_still_requires_a_reason(): void
    {
        $admin = $this->superAdmin();
        $broker = $this->broker(User::STATUS_ACTIVE);

        $this->actingAs($admin)
            ->post(route('admin.approvals.reject', $broker), ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->assertSame(User::STATUS_ACTIVE, $broker->fresh()->status);
    }

    public function test_a_decided_broker_page_offers_the_opposite_action(): void
    {
        $admin = $this->superAdmin();
        $broker = $this->broker(User::STATUS_ACTIVE);

        $response = $this->actingAs($admin)->get(route('admin.approvals.show', $broker));

        $response->assertOk();
        $response->assertSee('Revoke access');
        $response->assertSee('already been decided');
        // Asserted on the verb only — the noun ("broker" vs "channel partner") is copy that
        // has changed once already and is not what this test is about.
        $response->assertDontSee('Re-approve');

        $rejected = $this->actingAs($admin)->get(route('admin.approvals.show', $this->broker(User::STATUS_REJECTED)));
        $rejected->assertSee('Re-approve');
        // A rejected registration has nothing left to revoke.
        $rejected->assertDontSee('Revoke access');
    }

    // ------------------------------------------------------------------ leads

    public function test_a_lead_row_opens_its_detail_page(): void
    {
        $lead = $this->lead();

        $this->actingAs($this->superAdmin())
            ->get(route('admin.leads'))
            ->assertOk()
            ->assertSee(route('admin.leads.show', $lead), false);
    }

    public function test_the_lead_detail_page_shows_broker_project_and_gate_state(): void
    {
        $lead = $this->lead();

        $response = $this->actingAs($this->superAdmin())->get(route('admin.leads.show', $lead));

        $response->assertOk();
        $response->assertSee('B. Broker');
        $response->assertSee('Broker &amp; Co', false);
        $response->assertSee('RERA-BRK-1');
        $response->assertSee('Azure Bay Residences');
        $response->assertSee('Skyline Realty Group');
        // Every stage is offered, not just the ones ahead of the current one.
        foreach (['Viewed', 'Interested', 'Accepted', 'Declined'] as $stage) {
            $response->assertSee($stage);
        }
        $response->assertSee('Hidden from the developer.');
    }

    public function test_accepting_a_lead_unlocks_contact_and_stamps_the_response(): void
    {
        $lead = $this->lead();

        $this->actingAs($this->superAdmin())
            ->patch(route('admin.leads.update', $lead), ['status' => Lead::STATUS_ACCEPTED])
            ->assertSessionHasNoErrors();

        $lead->refresh();

        $this->assertSame(Lead::STATUS_ACCEPTED, $lead->status);
        $this->assertTrue($lead->contact_unlocked);
        $this->assertNotNull($lead->interested_at);
        $this->assertNotNull($lead->responded_at);
    }

    /** The whole point: an accepted lead can be declined, and a declined one reopened. */
    public function test_a_decided_lead_can_be_changed_in_either_direction(): void
    {
        $admin = $this->superAdmin();
        $lead = $this->lead();

        $this->actingAs($admin)->patch(route('admin.leads.update', $lead), ['status' => Lead::STATUS_ACCEPTED]);
        $this->assertSame(Lead::STATUS_ACCEPTED, $lead->fresh()->status);

        $this->actingAs($admin)->patch(route('admin.leads.update', $lead), [
            'status' => Lead::STATUS_DECLINED,
            'developer_note' => 'Client budget did not hold.',
        ]);

        $lead->refresh();
        $this->assertSame(Lead::STATUS_DECLINED, $lead->status);
        $this->assertSame('Client budget did not hold.', $lead->developer_note);
        $this->assertTrue($lead->contact_unlocked);

        $this->actingAs($admin)->patch(route('admin.leads.update', $lead), ['status' => Lead::STATUS_INTERESTED]);

        $lead->refresh();
        $this->assertSame(Lead::STATUS_INTERESTED, $lead->status);
        // Back before a response, so the response stamp is cleared rather than left stale.
        $this->assertNull($lead->responded_at);
        $this->assertNotNull($lead->interested_at);
    }

    public function test_reverting_a_lead_to_viewed_relocks_contact_and_clears_the_stamps(): void
    {
        $admin = $this->superAdmin();
        $lead = $this->lead();

        $this->actingAs($admin)->patch(route('admin.leads.update', $lead), ['status' => Lead::STATUS_ACCEPTED]);
        $this->actingAs($admin)->patch(route('admin.leads.update', $lead), ['status' => Lead::STATUS_VIEWED]);

        $lead->refresh();

        $this->assertSame(Lead::STATUS_VIEWED, $lead->status);
        $this->assertFalse($lead->contact_unlocked);
        $this->assertNull($lead->interested_at);
        $this->assertNull($lead->responded_at);
        // `viewed_at` is the one stamp that survives — the view really did happen.
        $this->assertNotNull($lead->viewed_at);
    }

    public function test_an_unknown_lead_stage_is_rejected(): void
    {
        $lead = $this->lead();

        $this->actingAs($this->superAdmin())
            ->patch(route('admin.leads.update', $lead), ['status' => 'closed_won'])
            ->assertSessionHasErrors('status');

        $this->assertSame(Lead::STATUS_VIEWED, $lead->fresh()->status);
    }
}
