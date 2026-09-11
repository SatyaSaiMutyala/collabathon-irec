<?php

namespace Tests\Feature;

use App\Models\BrokerProfile;
use App\Models\Developer;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Location-based ordering of the channel partner's developer directory.
 *
 * An admin pins a developer to a rank on its own page; the app shows that developer first
 * the moment a partner filters to that developer's city. The rank lives on the developer
 * row rather than in a developer-by-city table because a developer has exactly one `city`
 * and the directory is filtered on that same column — so these cover the two halves that
 * actually matter: the rank really does lead the list it belongs to, and it cannot leak
 * into a city it does not belong to.
 */
class DeveloperPriorityTest extends TestCase
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

    private function broker(): User
    {
        $broker = User::create([
            'name' => 'B. Broker',
            'email' => 'broker@example.test',
            'password' => 'password',
            'role' => User::ROLE_BROKER,
            'status' => User::STATUS_ACTIVE,
        ]);

        BrokerProfile::create([
            'user_id' => $broker->id,
            'company_name' => 'Broker & Co',
            'rera_number' => 'RERA-BRK-1',
            'city' => 'Hyderabad',
        ]);

        return $broker;
    }

    private function developer(string $name, string $city = 'Hyderabad', array $overrides = []): Developer
    {
        static $seq = 0;
        $seq++;

        // $overrides on the left: `+` keeps the left-hand value on a duplicate key, so the
        // defaults have to be the side that gives way.
        return Developer::create($overrides + [
            'company_name' => $name,
            'contact_person' => 'A. Contact',
            'email' => "dev{$seq}@example.test",
            'city' => $city,
            'cp_payout_percent' => 2.5,
            'status' => 'active',
        ]);
    }

    /** The company names the app would render, top to bottom, for one city. */
    private function directory(User $broker, ?string $city = 'Hyderabad'): array
    {
        $response = $this->actingAs($broker, 'sanctum')
            ->getJson('/api/v1/developers'.($city ? '?city='.urlencode($city) : ''))
            ->assertOk();

        return array_column($response->json('data'), 'company_name');
    }

    // ------------------------------------------------------------------ the app's order

    public function test_an_unpinned_directory_is_newest_first(): void
    {
        $broker = $this->broker();
        $this->developer('Alpha Estates');
        $this->developer('Bravo Realty');
        $this->developer('Charlie Homes');

        // The baseline every other test here is measured against — without it, a pinned
        // developer landing first proves nothing, since it might simply be the newest.
        $this->assertSame(
            ['Charlie Homes', 'Bravo Realty', 'Alpha Estates'],
            $this->directory($broker),
        );
    }

    public function test_a_pinned_developer_opens_its_city_directory(): void
    {
        $broker = $this->broker();
        $alpha = $this->developer('Alpha Estates');
        $this->developer('Bravo Realty');
        $this->developer('Charlie Homes');

        // The oldest of the three — last without a pin, first with one.
        $alpha->update(['priority' => 1]);

        $this->assertSame(
            ['Alpha Estates', 'Charlie Homes', 'Bravo Realty'],
            $this->directory($broker),
        );
    }

    public function test_rank_one_comes_before_rank_two(): void
    {
        $broker = $this->broker();
        $alpha = $this->developer('Alpha Estates');
        $bravo = $this->developer('Bravo Realty');
        $this->developer('Charlie Homes');

        // Deliberately pinned in the opposite order to the one they were created in, so
        // the result can only come from the ranks and not from recency.
        $alpha->update(['priority' => 2]);
        $bravo->update(['priority' => 1]);

        $this->assertSame(
            ['Bravo Realty', 'Alpha Estates', 'Charlie Homes'],
            $this->directory($broker),
        );
    }

    public function test_a_pin_in_another_city_never_reorders_this_one(): void
    {
        $broker = $this->broker();
        $this->developer('Alpha Estates');
        $this->developer('Bravo Realty');

        // Rank 1 in Bengaluru. The Hyderabad list must not see it at all — neither as a
        // row nor as something that pushed a Hyderabad company down.
        $this->developer('Deccan Builders', 'Bengaluru')->update(['priority' => 1]);

        $this->assertSame(['Bravo Realty', 'Alpha Estates'], $this->directory($broker));
        $this->assertSame(['Deccan Builders'], $this->directory($broker, 'Bengaluru'));
    }

    public function test_pins_still_lead_when_the_partner_sorts_by_name(): void
    {
        $broker = $this->broker();
        $this->developer('Alpha Estates');
        $this->developer('Bravo Realty');
        $this->developer('Zenith Towers')->update(['priority' => 1]);

        // A-Z would put Zenith last. A pin is a decision about the top of the list, so it
        // survives the sort rather than being one more thing the sort reshuffles.
        $response = $this->actingAs($broker, 'sanctum')
            ->getJson('/api/v1/developers?city=Hyderabad&sort=name&direction=asc')
            ->assertOk();

        $this->assertSame(
            ['Zenith Towers', 'Alpha Estates', 'Bravo Realty'],
            array_column($response->json('data'), 'company_name'),
        );
    }

    public function test_a_paused_developer_stays_out_of_the_directory_however_it_is_ranked(): void
    {
        $broker = $this->broker();
        $this->developer('Alpha Estates');
        $this->developer('Ghost Developments', 'Hyderabad', ['status' => 'paused'])
            ->update(['priority' => 1]);

        // Rank 1 is an ordering instruction, never a licence to appear.
        $this->assertSame(['Alpha Estates'], $this->directory($broker));
    }

    public function test_clearing_a_rank_returns_the_developer_to_the_normal_order(): void
    {
        $broker = $this->broker();
        $alpha = $this->developer('Alpha Estates');
        $this->developer('Bravo Realty');

        $alpha->update(['priority' => 1]);
        $this->assertSame(['Alpha Estates', 'Bravo Realty'], $this->directory($broker));

        $alpha->update(['priority' => null]);
        $this->assertSame(['Bravo Realty', 'Alpha Estates'], $this->directory($broker));
    }

    // ------------------------------------------------------------------ the admin panel

    public function test_an_admin_sets_and_clears_the_rank_from_the_developer_page(): void
    {
        $admin = $this->superAdmin();
        $developer = $this->developer('Alpha Estates');

        $this->actingAs($admin)
            ->patch("/admin/developers/{$developer->id}", ['status' => 'active', 'priority' => '1'])
            ->assertRedirect();

        $this->assertSame(1, $developer->fresh()->priority);

        // An emptied number input arrives as a blank string and is converted to null
        // upstream — this is the path an admin takes to un-pin, so it is the one worth
        // pinning down.
        $this->actingAs($admin)
            ->patch("/admin/developers/{$developer->id}", ['status' => 'active', 'priority' => ''])
            ->assertRedirect();

        $this->assertNull($developer->fresh()->priority);
    }

    public function test_pausing_an_account_leaves_the_rank_untouched(): void
    {
        $admin = $this->superAdmin();
        $developer = $this->developer('Alpha Estates');
        $developer->update(['priority' => 1]);

        // Exactly what the row menu's Pause action posts: a status and nothing else. The
        // `sometimes` rule on priority is what keeps this from silently un-pinning.
        $this->actingAs($admin)
            ->patch("/admin/developers/{$developer->id}", ['status' => 'paused'])
            ->assertRedirect();

        $developer->refresh();
        $this->assertSame('paused', $developer->status);
        $this->assertSame(1, $developer->priority);
    }

    public function test_the_rank_is_bounded_to_a_real_position(): void
    {
        $admin = $this->superAdmin();
        $developer = $this->developer('Alpha Estates');

        foreach (['0', '-1', '1000', 'first'] as $rejected) {
            $this->actingAs($admin)
                ->patch("/admin/developers/{$developer->id}", ['status' => 'active', 'priority' => $rejected])
                ->assertSessionHasErrors('priority');
        }

        $this->assertNull($developer->fresh()->priority);
    }

    /*
     * Two companies on the same number is the one state a ranked list cannot be left in:
     * the whole point of the number is to answer "who is first", and a tie hands that
     * answer back to whatever order the database returns. So taking a held rank moves
     * the company that held it, rather than warning and leaving the tie in place.
     */
    public function test_taking_a_held_rank_swaps_the_two(): void
    {
        $admin = $this->superAdmin();
        $broker = $this->broker();

        $alpha = $this->developer('Alpha Estates', overrides: ['priority' => 1]);
        $charlie = $this->developer('Charlie Homes', overrides: ['priority' => 5]);

        $this->actingAs($admin)
            ->patch("/admin/developers/{$charlie->id}", ['status' => 'active', 'priority' => '1'])
            ->assertRedirect();

        // They trade places: Charlie takes the top, Alpha takes the seat Charlie left.
        $this->assertSame(1, $charlie->fresh()->priority);
        $this->assertSame(5, $alpha->fresh()->priority);

        // And the app agrees - a swap the admin page reports but the directory ignores
        // would be the same bug wearing a better message.
        $this->assertSame(['Charlie Homes', 'Alpha Estates'], $this->directory($broker));

        // Named, both numbers given: an admin who did not expect the move needs to know
        // where the other company went without hunting for it.
        $this->assertStringContainsString('Alpha Estates', session('success'));
        $this->assertStringContainsString('priority 1 to 5', session('success'));
    }

    public function test_a_developer_arriving_from_nowhere_sends_the_holder_to_the_end(): void
    {
        $admin = $this->superAdmin();
        $broker = $this->broker();

        $alpha = $this->developer('Alpha Estates', overrides: ['priority' => 1]);
        $bravo = $this->developer('Bravo Realty', overrides: ['priority' => 2]);
        $charlie = $this->developer('Charlie Homes');   // never pinned

        $this->actingAs($admin)
            ->patch("/admin/developers/{$charlie->id}", ['status' => 'active', 'priority' => '1'])
            ->assertRedirect();

        // Nothing to trade back, so Alpha goes behind everyone already pinned - it keeps
        // a place rather than being quietly unpinned, which is the destructive reading of
        // "swap" when one side has nothing to give.
        $this->assertSame(1, $charlie->fresh()->priority);
        $this->assertSame(3, $alpha->fresh()->priority);
        $this->assertSame(2, $bravo->fresh()->priority, 'Only the company on the taken rank moves.');

        $this->assertSame(['Charlie Homes', 'Bravo Realty', 'Alpha Estates'], $this->directory($broker));
    }

    public function test_a_swap_stays_inside_the_city_it_happens_in(): void
    {
        $admin = $this->superAdmin();

        // Same number, different city - two separate first places, not a collision.
        $deccan = $this->developer('Deccan Builders', 'Bengaluru', ['priority' => 1]);
        $alpha = $this->developer('Alpha Estates', overrides: ['priority' => 5]);

        $this->actingAs($admin)
            ->patch("/admin/developers/{$alpha->id}", ['status' => 'active', 'priority' => '1'])
            ->assertRedirect();

        $this->assertSame(1, $deccan->fresh()->priority, 'Bengaluru keeps its own order.');
        $this->assertStringNotContainsString('Deccan Builders', session('success'));
    }

    public function test_a_paused_developer_still_gives_up_the_rank_it_is_holding(): void
    {
        $admin = $this->superAdmin();

        // Out of the directory today, but the rank is on the row: leaving it where it is
        // would hand back a duplicate the moment the account is reactivated.
        $ghost = $this->developer('Ghost Developments', overrides: ['status' => 'paused', 'priority' => 1]);
        $charlie = $this->developer('Charlie Homes', overrides: ['priority' => 5]);

        $this->actingAs($admin)
            ->patch("/admin/developers/{$charlie->id}", ['status' => 'active', 'priority' => '1'])
            ->assertRedirect();

        $this->assertSame(5, $ghost->fresh()->priority);
        $this->assertSame(1, $charlie->fresh()->priority);
    }

    public function test_clearing_a_rank_moves_nobody(): void
    {
        $admin = $this->superAdmin();
        $alpha = $this->developer('Alpha Estates', overrides: ['priority' => 1]);
        $bravo = $this->developer('Bravo Realty', overrides: ['priority' => 2]);

        $this->actingAs($admin)
            ->patch("/admin/developers/{$bravo->id}", ['status' => 'active', 'priority' => ''])
            ->assertRedirect();

        // Giving up a place is not a swap - there is no one to trade with.
        $this->assertNull($bravo->fresh()->priority);
        $this->assertSame(1, $alpha->fresh()->priority);
        $this->assertStringNotContainsString('moved from priority', session('success'));
    }

    public function test_a_duplicate_left_by_an_earlier_save_is_settled_on_the_next_one(): void
    {
        $admin = $this->superAdmin();

        // The shape older saves could leave behind, before taking a rank moved anyone.
        $alpha = $this->developer('Alpha Estates', overrides: ['priority' => 1]);
        $bravo = $this->developer('Bravo Realty', overrides: ['priority' => 1]);

        // Re-saving Bravo on the number it already sits on vacates nothing, so handing
        // Alpha that same number would leave the tie exactly as it was.
        $this->actingAs($admin)
            ->patch("/admin/developers/{$bravo->id}", ['status' => 'active', 'priority' => '1'])
            ->assertRedirect();

        $this->assertSame(1, $bravo->fresh()->priority);
        $this->assertSame(2, $alpha->fresh()->priority);
    }

    public function test_a_developer_added_straight_onto_a_held_rank_takes_it(): void
    {
        $admin = $this->superAdmin();
        $alpha = $this->developer('Alpha Estates', overrides: ['priority' => 1]);

        $this->actingAs($admin)
            ->post('/admin/developers', [
                'company_name' => 'Charlie Homes',
                'contact_person' => 'C. Contact',
                'email' => 'charlie@example.test',
                'mobile' => '9000000123',
                'city' => 'Hyderabad',
                'cp_payout_percent' => '2.5',
                'status' => 'active',
                'priority' => '1',
            ])
            ->assertRedirect();

        // Adding a company is the other way onto a rank, and it has to settle the same
        // way - otherwise the tie is simply created through a different door.
        $charlie = Developer::where('company_name', 'Charlie Homes')->sole();

        $this->assertSame(1, $charlie->priority);
        $this->assertSame(2, $alpha->fresh()->priority);
        $this->assertStringContainsString('Alpha Estates', session('success'));
    }

    public function test_the_developer_page_states_the_rank_as_a_position_in_its_city(): void
    {
        $admin = $this->superAdmin();
        $developer = $this->developer('Alpha Estates');

        // Unpinned first: the page has to say so rather than leave a blank where a number
        // would go, which reads as a missing value instead of a deliberate one.
        $this->actingAs($admin)
            ->get("/admin/developers/{$developer->id}")
            ->assertOk()
            ->assertSee('Directory priority')
            ->assertSee('Not pinned');

        $developer->update(['priority' => 1]);

        $this->actingAs($admin)
            ->get("/admin/developers/{$developer->id}")
            ->assertOk()
            ->assertSee('#1')
            ->assertSee('Position 1 for partners browsing');
    }

    public function test_the_rank_can_be_exported_with_the_directory(): void
    {
        $admin = $this->superAdmin();
        $this->developer('Alpha Estates')->update(['priority' => 1]);
        $this->developer('Bravo Realty');

        $response = $this->actingAs($admin)
            ->get('/admin/developers?export=excel&columns=company,priority')
            ->assertOk();

        $csv = $response->streamedContent();

        // Company names carry spaces, so the writer quotes them — match the quoted form
        // rather than the bare one.
        $this->assertStringContainsString('Directory priority', $csv);
        $this->assertStringContainsString('"Alpha Estates",1', $csv);
        // The unpinned row still has the column, just empty — an absent rank is a blank
        // cell, never a 0 that would read as a real position.
        $this->assertStringContainsString('"Bravo Realty",', $csv);
    }

    public function test_the_admin_list_orders_by_rank_the_way_the_app_does(): void
    {
        $admin = $this->superAdmin();
        $this->developer('Alpha Estates')->update(['priority' => 2]);
        $this->developer('Bravo Realty')->update(['priority' => 1]);
        $this->developer('Charlie Homes');

        // City filter plus the Priority header is how an admin reads back what a partner
        // browsing that city will see — so it has to produce the same order.
        $response = $this->actingAs($admin)
            ->get('/admin/developers?city=Hyderabad&sort=priority&direction=asc')
            ->assertOk();

        $this->assertSame(
            ['Bravo Realty', 'Alpha Estates', 'Charlie Homes'],
            $response->viewData('developers')->pluck('company_name')->all(),
        );
    }
}
