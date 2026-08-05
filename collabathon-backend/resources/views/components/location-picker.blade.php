@props([
    'country' => null,
    'state' => null,
    'city' => null,
    'countryName' => 'country',
    'stateName' => 'state',
    'cityName' => 'city',
    'required' => false,
])

@php
use App\Models\Country;

/**
 * Cascading Country -> State -> City selects, backed by Settings -> Locations.
 *
 * Submits NAMES, not ids. `developers.country/state/city` are varchar columns and every
 * existing row holds a name, so submitting ids would mean a migration plus a back-fill for
 * a change that is only about how the value is picked.
 *
 * A stored value that is not in the master list is merged back in and marked, rather than
 * dropped. Without that, opening the edit form for a developer whose state was typed
 * before this existed would show an empty select, and saving the form — without the admin
 * touching either field — would silently blank their location.
 */
$tree = Country::with(['states' => fn ($q) => $q->orderBy('name')->with(['cities' => fn ($c) => $c->orderBy('name')])])
    ->orderBy('name')
    ->get()
    ->mapWithKeys(fn ($country) => [
        $country->name => $country->states
            ->mapWithKeys(fn ($s) => [$s->name => $s->cities->pluck('name')->all()])
            ->all(),
    ])
    ->all();

// old() first so a failed submit keeps what was chosen.
$currentCountry = old($countryName, $country);
$currentState = old($stateName, $state);
$currentCity = old($cityName, $city);

/*
 * Merge back anything stored that the master list does not have, at whichever level it is
 * missing. The five developers created before Locations existed hold state "UAE", which is
 * a country name — without this their row would open with empty selects and saving any
 * unrelated field would blank their location.
 */
$legacy = [];

if (blank($currentCountry) && filled($currentState)) {
    /*
     * Pre-Locations rows have no country at all. Prefer the country that owns the stored
     * state; when none does, the country stays EMPTY rather than getting a placeholder
     * label. An earlier attempt parked those rows under a literal "—", which displayed
     * fine and then saved "—" into developers.country the next time anyone touched the
     * form. The empty string is both a valid "not set" and a usable bucket key.
     */
    $owner = collect($tree)->search(fn ($states) => array_key_exists($currentState, $states));
    $currentCountry = $owner !== false ? $owner : '';
}

// '' is a real bucket here — it holds the state/city of a row whose country is unknown.
$countryKey = $currentCountry ?? '';

if (! array_key_exists($countryKey, $tree)) {
    $tree[$countryKey] = [];
    if (filled($countryKey)) {
        $legacy[] = $countryKey;
    }
}
if (filled($currentState) && ! array_key_exists($currentState, $tree[$countryKey])) {
    $tree[$countryKey][$currentState] = [];
    $legacy[] = $currentState;
}
if (filled($currentState) && filled($currentCity)
    && ! in_array($currentCity, $tree[$countryKey][$currentState], true)) {
    $tree[$countryKey][$currentState][] = $currentCity;
    $legacy[] = $currentCity;
}

$countryError = $errors->has($countryName);
$stateError = $errors->has($stateName);
$cityError = $errors->has($cityName);
$selectClass = 'w-full h-10 pl-3.5 pr-9 rounded-lg bg-panel border text-[13.5px] text-ink appearance-none
    focus:outline-none focus:ring-[3px] transition-[border-color,box-shadow] ';
@endphp

{{--
    `state` and `city` start empty and are assigned in init(), one tick after mount. Both
    halves of that matter:

    - $nextTick, because only the country options come from Blade and exist immediately.
      State and city options come from the x-for blocks below. At first paint those
      selects hold only their placeholder, so a value assigned then has no matching
      <option>, the browser drops it, and the field falls back to its placeholder.

    - starting empty, because Alpine only runs x-model's effect when the value actually
      changes. Seeding a select with the wanted value and then re-assigning the same
      string in init() is a no-op — the data reads correctly while the select stays blank,
      which is exactly how this failed the first time. '' -> 'Hyderabad' is a real change.

    Note for anyone editing the x-data expression: it is an HTML attribute delimited by
    double quotes, so it must not contain any. A JS comment holding a quoted string in
    there closes the attribute early and Alpine then skips the whole component silently —
    no console error, the selects simply stop being reactive.
--}}
<div class="contents"
     x-data="{
         tree: @js($tree),
         country: @js($currentCountry ?? ''),
         state: '',
         city: '',
         get states() { return Object.keys(this.tree[this.country] ?? {}) },
         get cities() { return (this.tree[this.country] ?? {})[this.state] ?? [] },
         init() {
             const wantState = @js($currentState ?? '');
             const wantCity = @js($currentCity ?? '');
             this.$nextTick(() => {
                 if (this.states.includes(wantState)) { this.state = wantState }
                 this.$nextTick(() => {
                     if (this.cities.includes(wantCity)) { this.city = wantCity }
                 });
             });
         },
         onCountryChange() {
             if (! this.states.includes(this.state)) { this.state = ''; this.city = '' }
         },
         onStateChange() {
             // Keep the city only when it exists under the newly chosen state; otherwise
             // the form would post a city that belongs to a different one.
             if (! this.cities.includes(this.city)) { this.city = '' }
         },
     }">

    {{-- Country ----------------------------------------------------------- --}}
    <div>
        <label for="{{ $countryName }}" class="flex items-center gap-1 text-[12.5px] font-medium text-ink mb-1.5">
            Country
            @if($required)<span class="text-danger" aria-hidden="true">*</span>@endif
        </label>
        <div class="relative">
            <select id="{{ $countryName }}" name="{{ $countryName }}"
                    x-model="country" @change="onCountryChange()"
                    @if($required) required @endif
                    class="{{ $selectClass }}{{ $countryError ? 'border-danger focus:border-danger focus:ring-danger-ring' : 'border-line focus:border-primary focus:ring-primary-ring' }}">
                <option value="">Select a country</option>
                {{-- The '' bucket is the unknown-country holder, not a real option; it
                     would render as a second blank entry below the placeholder. --}}
                @foreach(array_filter(array_keys($tree), 'filled') as $countryOption)
                    <option value="{{ $countryOption }}">{{ $countryOption }}</option>
                @endforeach
            </select>
            <x-icon name="chevron-down" class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-ink-3 pointer-events-none" />
        </div>
        @error($countryName)
            <p class="flex items-start gap-1.5 text-[11.5px] text-danger mt-1.5">
                <x-icon name="x" class="w-3.5 h-3.5 shrink-0 mt-px" /><span>{{ $message }}</span>
            </p>
        @enderror
    </div>

    {{-- State ------------------------------------------------------------- --}}
    <div>
        <label for="{{ $stateName }}" class="flex items-center gap-1 text-[12.5px] font-medium text-ink mb-1.5">
            State / Emirate
            @if($required)<span class="text-danger" aria-hidden="true">*</span>@endif
        </label>
        <div class="relative">
            <select id="{{ $stateName }}" name="{{ $stateName }}"
                    x-model="state" @change="onStateChange()"
                    {{-- Keyed off available options, not off `country` being truthy: a
                         pre-Locations row has no country but does have a state to keep,
                         and disabling the select would drop it on the next save. --}}
                    :disabled="states.length === 0"
                    @if($required) required @endif
                    class="{{ $selectClass }}disabled:bg-canvas disabled:text-ink-3 {{ $stateError ? 'border-danger focus:border-danger focus:ring-danger-ring' : 'border-line focus:border-primary focus:ring-primary-ring' }}">
                <option value="" x-text="states.length ? 'Select a state' : 'Choose a country first'"></option>
                <template x-for="option in states" :key="option">
                    <option :value="option" x-text="option"></option>
                </template>
            </select>
            <x-icon name="chevron-down" class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-ink-3 pointer-events-none" />
        </div>
        @error($stateName)
            <p class="flex items-start gap-1.5 text-[11.5px] text-danger mt-1.5">
                <x-icon name="x" class="w-3.5 h-3.5 shrink-0 mt-px" /><span>{{ $message }}</span>
            </p>
        @enderror
    </div>

    {{-- City -------------------------------------------------------------- --}}
    <div>
        <label for="{{ $cityName }}" class="flex items-center gap-1 text-[12.5px] font-medium text-ink mb-1.5">
            City
            @if($required)<span class="text-danger" aria-hidden="true">*</span>@endif
        </label>
        <div class="relative">
            <select id="{{ $cityName }}" name="{{ $cityName }}"
                    x-model="city"
                    :disabled="cities.length === 0"
                    @if($required) required @endif
                    class="{{ $selectClass }}disabled:bg-canvas disabled:text-ink-3 {{ $cityError ? 'border-danger focus:border-danger focus:ring-danger-ring' : 'border-line focus:border-primary focus:ring-primary-ring' }}">
                <option value="" x-text="cities.length ? 'Select a city' : 'Choose a state first'"></option>
                <template x-for="option in cities" :key="option">
                    <option :value="option" x-text="option"></option>
                </template>
            </select>
            <x-icon name="chevron-down" class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-ink-3 pointer-events-none" />
        </div>
        @error($cityName)
            <p class="flex items-start gap-1.5 text-[11.5px] text-danger mt-1.5">
                <x-icon name="x" class="w-3.5 h-3.5 shrink-0 mt-px" /><span>{{ $message }}</span>
            </p>
        @else
            <p class="text-[11.5px] text-ink-3 mt-1.5" x-show="state && cities.length === 0" x-cloak>
                No cities under this state yet — add them in Settings → Locations.
            </p>
        @enderror
    </div>

    {{-- One notice for the whole cascade rather than one per select: a pre-Locations row
         is usually missing at more than one level, and three stacked warnings for a
         single stale address is noise. --}}
    @if($legacy)
        <div class="sm:col-span-2">
            <p class="text-[11.5px] text-ink-3">
                {{ collect($legacy)->map(fn ($v) => '“' . $v . '”')->join(', ', ' and ') }}
                {{ count($legacy) === 1 ? 'is' : 'are' }} not in Settings → Locations yet.
                Kept so saving does not change {{ count($legacy) === 1 ? 'it' : 'them' }}.
            </p>
        </div>
    @endif
</div>
