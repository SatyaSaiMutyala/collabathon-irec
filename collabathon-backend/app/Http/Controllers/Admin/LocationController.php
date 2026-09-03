<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Country;
use App\Models\State;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Location master data — the country/state/city lists the developer and project forms
 * pick from.
 *
 * The three levels share one controller because they are one cascade, not three
 * independent resources: a state is meaningless without its country, and every action
 * here has to return to the same settings panel with the selection intact. Splitting
 * them would mean three controllers all redirecting to the same place with the same
 * query string.
 *
 * Every redirect carries `country`/`state` so the panel reopens where the admin was
 * rather than collapsing back to the first country after each save.
 */
class LocationController extends Controller
{
    /**
     * Keeps the cascade's position across a save — for a real submit, a redirect back
     * to the settings page with the same country/state selected; for the settings
     * page's own fetch-based submit (see app.js), the message plus that same selection
     * as `params`, which is what tells the client which country/state to refetch with
     * rather than whatever happened to already be in the address bar (an add always
     * has to select the thing just added, not the previous selection).
     */
    private function backTo(Request $request, array $overrides, string $message): RedirectResponse|JsonResponse
    {
        $params = array_filter([
            'country' => $overrides['country'] ?? $request->input('country_id') ?: $request->query('country'),
            'state' => $overrides['state'] ?? $request->query('state'),
        ]);

        if ($request->ajax()) {
            return response()->json(['message' => $message, 'params' => $params]);
        }

        // `tab` rather than a #locations fragment: a fragment never reaches the server, so
        // the settings page could not know to reopen this tab and every save bounced the
        // admin back to Form fields. Always set, even when the cascade has no selection
        // left — deleting the last country still has to land back here.
        return redirect()->route('admin.settings', $params + ['tab' => 'locations'])->with('status', $message);
    }

    // ------------------------------------------------------------------ countries

    public function storeCountry(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:96', Rule::unique('countries', 'name')],
            'code' => ['nullable', 'string', 'max:8'],
        ]);

        $country = Country::create($data);

        return $this->backTo($request, ['country' => $country->id], "Country “{$country->name}” added.");
    }

    public function updateCountry(Request $request, Country $country): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:96', Rule::unique('countries', 'name')->ignore($country->id)],
            'code' => ['nullable', 'string', 'max:8'],
        ]);

        $country->update($data);

        return $this->backTo($request, ['country' => $country->id], 'Country updated.');
    }

    /**
     * Moves the country — and everything under it — to Trash. Reversible per row, see
     * restoreCountry(); restoring the country does not also restore its states/cities,
     * each is its own row in Trash.
     *
     * The database's cascade only fires on a real DELETE — this is a soft one (an
     * UPDATE), so cascading down to states and their cities has to happen here by
     * hand, or they would keep showing up everywhere this country no longer does. See
     * forceDeleteCountry() for the irreversible version, where the real cascade fires.
     */
    public function destroyCountry(Request $request, Country $country): RedirectResponse|JsonResponse
    {
        $name = $country->name;
        $stateIds = State::where('country_id', $country->id)->pluck('id');

        City::whereIn('state_id', $stateIds)->delete();
        State::where('country_id', $country->id)->delete();
        $country->delete();

        return $this->backTo($request, ['country' => null, 'state' => null], "Country “{$name}” and everything under it were moved to Trash.");
    }

    /** Undoes destroyCountry() — only this row; its states/cities are restored separately. */
    public function restoreCountry(int $country): RedirectResponse
    {
        $country = Country::onlyTrashed()->findOrFail($country);
        $country->restore();

        return redirect()->route('admin.trash')->with('success', "Country “{$country->name}” was restored.");
    }

    /** The irreversible version of destroyCountry() — only reachable from Trash. */
    public function forceDeleteCountry(int $country): RedirectResponse
    {
        $country = Country::onlyTrashed()->findOrFail($country);
        $name = $country->name;
        // A real DELETE this time — the database's own cascade removes every state and
        // city under it for good, trashed or not.
        $country->forceDelete();

        return redirect()->route('admin.trash')
            ->with('warning', "Country “{$name}” and everything under it were permanently deleted.");
    }

    // ------------------------------------------------------------------ states

    public function storeState(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'country_id' => ['required', 'exists:countries,id'],
            'name' => [
                'required', 'string', 'max:96',
                // Scoped, not global: two countries may both have a "Punjab".
                Rule::unique('states', 'name')->where('country_id', $request->input('country_id')),
            ],
        ]);

        $state = State::create($data);

        return $this->backTo($request, ['country' => $state->country_id, 'state' => $state->id], "State “{$state->name}” added.");
    }

    public function updateState(Request $request, State $state): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:96',
                Rule::unique('states', 'name')->where('country_id', $state->country_id)->ignore($state->id),
            ],
        ]);

        $state->update($data);

        return $this->backTo($request, ['country' => $state->country_id, 'state' => $state->id], 'State updated.');
    }

    /** Moves the state — and its cities — to Trash. See destroyCountry()'s note on why the cascade is manual here. */
    public function destroyState(Request $request, State $state): RedirectResponse|JsonResponse
    {
        $name = $state->name;
        $countryId = $state->country_id;

        City::where('state_id', $state->id)->delete();
        $state->delete();

        return $this->backTo($request, ['country' => $countryId, 'state' => null], "State “{$name}” and its cities were moved to Trash.");
    }

    /** Undoes destroyState() — only this row; its cities are restored separately. */
    public function restoreState(int $state): RedirectResponse
    {
        $state = State::onlyTrashed()->findOrFail($state);
        $state->restore();

        return redirect()->route('admin.trash')->with('success', "State “{$state->name}” was restored.");
    }

    /** The irreversible version of destroyState() — only reachable from Trash. */
    public function forceDeleteState(int $state): RedirectResponse
    {
        $state = State::onlyTrashed()->findOrFail($state);
        $name = $state->name;
        $state->forceDelete();

        return redirect()->route('admin.trash')->with('warning', "State “{$name}” and its cities were permanently deleted.");
    }

    // ------------------------------------------------------------------ cities

    public function storeCity(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'state_id' => ['required', 'exists:states,id'],
            'name' => [
                'required', 'string', 'max:96',
                Rule::unique('cities', 'name')->where('state_id', $request->input('state_id')),
            ],
        ]);

        $city = City::create($data);

        return $this->backTo($request, [
            'country' => $city->state->country_id,
            'state' => $city->state_id,
        ], "City “{$city->name}” added.");
    }

    public function updateCity(Request $request, City $city): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:96',
                Rule::unique('cities', 'name')->where('state_id', $city->state_id)->ignore($city->id),
            ],
        ]);

        $city->update($data);

        return $this->backTo($request, [
            'country' => $city->state->country_id,
            'state' => $city->state_id,
        ], 'City updated.');
    }

    /** Moves the city to Trash — reversible, see restoreCity(). */
    public function destroyCity(Request $request, City $city): RedirectResponse|JsonResponse
    {
        $name = $city->name;
        $stateId = $city->state_id;
        $countryId = $city->state->country_id;
        $city->delete();

        return $this->backTo($request, ['country' => $countryId, 'state' => $stateId], "City “{$name}” was moved to Trash.");
    }

    /** Undoes destroyCity(). */
    public function restoreCity(int $city): RedirectResponse
    {
        $city = City::onlyTrashed()->findOrFail($city);
        $city->restore();

        return redirect()->route('admin.trash')->with('success', "City “{$city->name}” was restored.");
    }

    /** The irreversible version of destroyCity() — only reachable from Trash. */
    public function forceDeleteCity(int $city): RedirectResponse
    {
        $city = City::onlyTrashed()->findOrFail($city);
        $name = $city->name;
        $city->forceDelete();

        return redirect()->route('admin.trash')->with('warning', "City “{$name}” was permanently deleted.");
    }
}
