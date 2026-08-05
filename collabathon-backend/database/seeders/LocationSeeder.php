<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Country;
use App\Models\State;
use Illuminate\Database\Seeder;

/**
 * Starting country/state/city data for the pickers.
 *
 * Shaped to match how the existing records actually use the two columns, not to an
 * idealised gazetteer: `developers.state` already holds an emirate/state name and
 * `developers.city` a city inside it, so the seed has to line up with those or the first
 * dropdown render would fail to match a single stored value.
 *
 * firstOrCreate throughout, so re-running is safe and anything the admin has added or
 * renamed through Settings -> Locations survives.
 */
class LocationSeeder extends Seeder
{
    /** country => [ code, [ state => [cities] ] ] */
    private const TREE = [
        'India' => ['IN', [
            'Telangana' => ['Hyderabad', 'Warangal', 'Nizamabad', 'Karimnagar'],
            'Andhra Pradesh' => ['Visakhapatnam', 'Vijayawada', 'Guntur', 'Tirupati'],
            'Karnataka' => ['Bengaluru', 'Mysuru', 'Mangaluru'],
            'Maharashtra' => ['Mumbai', 'Pune', 'Nagpur'],
            'Tamil Nadu' => ['Chennai', 'Coimbatore', 'Madurai'],
        ]],
        'United Arab Emirates' => ['AE', [
            // Emirates are the state level here; each carries its primary city plus the
            // localities a listing is actually written against.
            'Dubai' => ['Dubai', 'Deira', 'Jumeirah', 'Business Bay', 'Palm Jumeirah', 'Dubai Marina'],
            'Abu Dhabi' => ['Abu Dhabi', 'Al Ain', 'Yas Island', 'Saadiyat Island'],
            'Sharjah' => ['Sharjah', 'Al Majaz', 'Muwailih'],
            'Ajman' => ['Ajman', 'Al Nuaimiya'],
            'Ras Al Khaimah' => ['Ras Al Khaimah'],
            'Fujairah' => ['Fujairah'],
            'Umm Al Quwain' => ['Umm Al Quwain'],
        ]],
    ];

    public function run(): void
    {
        foreach (self::TREE as $countryName => [$code, $states]) {
            $country = Country::firstOrCreate(['name' => $countryName], ['code' => $code]);

            foreach ($states as $stateName => $cities) {
                $state = State::firstOrCreate([
                    'country_id' => $country->id,
                    'name' => $stateName,
                ]);

                foreach ($cities as $cityName) {
                    City::firstOrCreate(['state_id' => $state->id, 'name' => $cityName]);
                }
            }
        }
    }
}
