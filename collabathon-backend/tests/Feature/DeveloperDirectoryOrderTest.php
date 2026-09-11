<?php

namespace Tests\Feature;

use App\Models\BrokerProfile;
use App\Models\City;
use App\Models\Country;
use App\Models\Developer;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What order the developer directory arrives in for a channel partner, and how far it
 * reaches.
 *
 * Two rules decide it, in this order: an admin's pin outranks everything, and after
 * that the nearest company comes first and the list works outward. The reach stops at
 * the partner's own state, so crossing into another one is a deliberate move on their
 * part rather than something the list does on its own.
 *
 * The rule this file exists to protect is the third one, which has no visible feature
 * attached to it: the list must never come back empty because of something we could
 * not work out. Filtering on the exact city name did exactly that - every developer
 * sits in one of a handful of city names, while a phone's GPS reverse-geocodes to
 * whatever OpenStreetMap calls that spot, so a partner standing in Secunderabad or
 * Kukatpally matched nothing and was shown nothing.
 */
class DeveloperDirectoryOrderTest extends TestCase
{
    use RefreshDatabase;

    /** Real points, so the distances between them are real distances. */
    private const BEGUMPET = ['lat' => 17.4435, 'lng' => 78.4645];

    private const BANJARA_HILLS = ['lat' => 17.4126, 'lng' => 78.4392];   //  ~4 km out

    private const GACHIBOWLI = ['lat' => 17.4401, 'lng' => 78.3489];      // ~12 km out

    private const SHAMSHABAD = ['lat' => 17.2403, 'lng' => 78.4294];      // ~23 km out

    private const WARANGAL = ['lat' => 17.9689, 'lng' => 79.5941];        // same state, far

    private const AHMEDABAD = ['lat' => 23.0225, 'lng' => 72.5714];       // another state

    private function broker(): User
    {
        $broker = User::create([
            'name' => 'Partner',
            'email' => 'partner@example.test',
            'password' => 'password',
            'role' => User::ROLE_BROKER,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        BrokerProfile::create(['user_id' => $broker->id, 'city' => 'Hyderabad']);

        return $broker;
    }

    /**
     * A point as the developers table stores it. The constants above are shaped for the
     * query string the app sends (`lat`/`lng`); the columns are named in full.
     */
    private function at(array $point): array
    {
        return ['latitude' => $point['lat'], 'longitude' => $point['lng']];
    }

    private function developer(string $name, array $overrides = []): Developer
    {
        static $seq = 0;
        $seq++;

        // $overrides first: `+` keeps the left-hand value on a duplicate key, so the
        // defaults have to be the side that gives way.
        return Developer::create($overrides + [
            'company_name' => $name,
            'contact_person' => 'A. Contact',
            'email' => "dev{$seq}@example.test",
            'city' => 'Hyderabad',
            'state' => 'Telangana',
            'cp_payout_percent' => 2.5,
            'status' => 'active',
        ]);
    }

    /** The cities master data the state cap is read from - the one list an admin curates. */
    private function knownCities(): void
    {
        $india = Country::create(['name' => 'India', 'code' => 'IN']);

        City::create([
            'state_id' => State::create(['country_id' => $india->id, 'name' => 'Telangana'])->id,
            'name' => 'Hyderabad',
        ]);

        City::create([
            'state_id' => State::create(['country_id' => $india->id, 'name' => 'Gujarat'])->id,
            'name' => 'Ahmedabad',
        ]);
    }

    /** The company names the app would render, top to bottom. */
    private function directory(User $broker, array $params = []): array
    {
        $response = $this->actingAs($broker, 'sanctum')
            ->getJson('/api/v1/developers?'.http_build_query($params))
            ->assertOk();

        return array_column($response->json('data'), 'company_name');
    }

    // ------------------------------------------------------------------ nearest first

    public function test_the_nearest_company_opens_the_list_and_the_rest_work_outward(): void
    {
        $broker = $this->broker();

        // Created in the wrong order on purpose: without distance this is the order the
        // list would fall back to, newest first, so a pass here means distance decided.
        $this->developer('Banjara Estates', $this->at(self::BANJARA_HILLS));
        $this->developer('Shamshabad Realty', $this->at(self::SHAMSHABAD));
        $this->developer('Gachibowli Homes', $this->at(self::GACHIBOWLI));

        $this->assertSame(
            ['Banjara Estates', 'Gachibowli Homes', 'Shamshabad Realty'],
            $this->directory($broker, self::BEGUMPET),
        );
    }

    public function test_a_pin_leads_the_list_however_far_away_it_is(): void
    {
        $broker = $this->broker();

        $this->developer('Banjara Estates', $this->at(self::BANJARA_HILLS));
        $this->developer('Warangal Builders', $this->at(self::WARANGAL) + ['priority' => 1]);

        // An admin's rank is a business decision about who gets seen first; distance is
        // only how the rest of the list arranges itself underneath it.
        $this->assertSame(
            ['Warangal Builders', 'Banjara Estates'],
            $this->directory($broker, self::BEGUMPET),
        );
    }

    public function test_distance_separates_two_companies_pinned_to_the_same_rank(): void
    {
        $broker = $this->broker();

        // Two cities in one state can each hold a rank 1 - the rank is per city, the
        // list is per state, so the two legitimately meet here and need separating.
        $this->developer('Warangal Builders', $this->at(self::WARANGAL) + ['city' => 'Warangal', 'priority' => 1]);
        $this->developer('Banjara Estates', $this->at(self::BANJARA_HILLS) + ['priority' => 1]);

        $this->assertSame(
            ['Banjara Estates', 'Warangal Builders'],
            $this->directory($broker, self::BEGUMPET),
        );
    }

    public function test_a_company_with_no_coordinates_is_listed_last_rather_than_dropped(): void
    {
        $broker = $this->broker();

        $this->developer('Nowhere Developments');   // admin never set a point
        $this->developer('Shamshabad Realty', $this->at(self::SHAMSHABAD));

        // A blank coordinate is a gap in the admin record. It is not a reason for a
        // partner to stop seeing a company that is otherwise active and listed.
        $this->assertSame(
            ['Shamshabad Realty', 'Nowhere Developments'],
            $this->directory($broker, self::BEGUMPET),
        );
    }

    // ------------------------------------------------------------------ how far it reaches

    public function test_the_list_stops_at_the_partner_s_own_state(): void
    {
        $this->knownCities();
        $broker = $this->broker();

        $this->developer('Banjara Estates', $this->at(self::BANJARA_HILLS));
        $this->developer('Gujarat Group', $this->at(self::AHMEDABAD) + ['city' => 'Ahmedabad', 'state' => 'Gujarat']);

        // Another state is a place the partner goes to deliberately, by moving the
        // location themselves - not somewhere the list wanders into on its own.
        $this->assertSame(
            ['Banjara Estates'],
            $this->directory($broker, self::BEGUMPET + ['city' => 'Hyderabad']),
        );
    }

    public function test_moving_the_location_reaches_the_other_state(): void
    {
        $this->knownCities();
        $broker = $this->broker();

        $this->developer('Banjara Estates', $this->at(self::BANJARA_HILLS));
        $this->developer('Gujarat Group', $this->at(self::AHMEDABAD) + ['city' => 'Ahmedabad', 'state' => 'Gujarat']);

        $this->assertSame(
            ['Gujarat Group'],
            $this->directory($broker, self::AHMEDABAD + ['city' => 'Ahmedabad']),
        );
    }

    public function test_a_city_the_admin_has_never_heard_of_still_returns_the_directory(): void
    {
        $this->knownCities();
        $broker = $this->broker();

        $this->developer('Banjara Estates', $this->at(self::BANJARA_HILLS));
        $this->developer('Shamshabad Realty', $this->at(self::SHAMSHABAD));

        // "Kukatpally" is inside Hyderabad, but it is not a row in the cities table and
        // it is not any developer's city either. Under the old exact-city filter this
        // returned nothing at all, which is the bug this whole path exists to close: an
        // unresolvable place now means "show everything, nearest first".
        $this->assertSame(
            ['Banjara Estates', 'Shamshabad Realty'],
            $this->directory($broker, self::BEGUMPET + ['city' => 'Kukatpally']),
        );
    }

    public function test_a_partner_whose_phone_gives_no_fix_still_gets_a_list(): void
    {
        $broker = $this->broker();

        $this->developer('Banjara Estates', $this->at(self::BANJARA_HILLS));
        $this->developer('Shamshabad Realty', $this->at(self::SHAMSHABAD));

        // No lat/lng at all - an older app build, or a denied location permission.
        // Nothing to measure from, so the list falls back to its normal order, but it
        // is still a list.
        $this->assertCount(2, $this->directory($broker));
    }

    public function test_a_nonsense_fix_is_ignored_rather_than_trusted(): void
    {
        $broker = $this->broker();

        $this->developer('Banjara Estates', $this->at(self::BANJARA_HILLS));
        $this->developer('Shamshabad Realty', $this->at(self::SHAMSHABAD));

        // Off the globe: a truncated or corrupted reading. Sorting by it would quietly
        // scramble the order with no sign anything went wrong.
        $this->assertCount(2, $this->directory($broker, ['lat' => '999', 'lng' => 'abc']));
    }

    // ------------------------------------------------------------------ what the row says

    public function test_each_row_carries_how_far_away_it_is(): void
    {
        $broker = $this->broker();

        $this->developer('Banjara Estates', $this->at(self::BANJARA_HILLS));
        $this->developer('Shamshabad Realty', $this->at(self::SHAMSHABAD));
        $this->developer('Nowhere Developments');

        $rows = $this->actingAs($broker, 'sanctum')
            ->getJson('/api/v1/developers?'.http_build_query(self::BEGUMPET))
            ->assertOk()
            ->json('data');

        $distances = array_column($rows, 'distance_km', 'company_name');

        // Real kilometres, not a sort key: the app puts this on the card, so a number
        // that only happened to sort correctly would read as wrong to a partner who
        // knows the city.
        $this->assertEqualsWithDelta(4.5, $distances['Banjara Estates'], 1.5);
        $this->assertEqualsWithDelta(23.0, $distances['Shamshabad Realty'], 2.0);
        $this->assertNull($distances['Nowhere Developments'], 'No point on file, no distance to claim.');
    }

    public function test_the_distance_is_left_out_when_there_is_nowhere_to_measure_from(): void
    {
        $broker = $this->broker();
        $this->developer('Banjara Estates', $this->at(self::BANJARA_HILLS));

        $rows = $this->actingAs($broker, 'sanctum')
            ->getJson('/api/v1/developers')
            ->assertOk()
            ->json('data');

        $this->assertNull($rows[0]['distance_km']);
    }
}
