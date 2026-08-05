@props(['latitude' => null, 'longitude' => null])

@php
/**
 * A map picker that fills *existing* fields rather than owning its own — unlike
 * x-address-finder (one address textarea + hidden coordinates), the project form already
 * has separate locality/landmark/pincode/full-address fields entered by hand. This only
 * wraps whichever of them the caller marks with `x-ref="latitude"` / `"longitude"` /
 * `"pincode"` / `"mapsLink"` in its slot, and fills only those on "Use this location" —
 * see locationFinder() in app.js.
 *
 * A wrapper, not a field of its own, because Alpine's $refs only resolve within the
 * component's own subtree: the fields it fills have to render inside this element,
 * not beside it.
 */
$currentLat = old('latitude', $latitude);
$currentLng = old('longitude', $longitude);
@endphp

<div x-data="locationFinder({
        endpoint: @js(route('admin.geocode')),
        latitude: @js($currentLat ?? ''),
        longitude: @js($currentLng ?? ''),
     })"
     @keydown.escape.window="open && closeMap()">

    {{ $slot }}

    {{-- Teleported to body for the same reason as x-address-finder: this sits inside the
         multi-step project form, itself not modal but tall and scrolling, and a
         non-teleported fixed overlay can still end up clipped by an ancestor's own
         stacking context. --}}
    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center px-4 py-8"
             role="dialog" aria-modal="true" aria-label="Pick the project's location on the map">

            <div class="fixed inset-0 bg-scrim" @click="closeMap()"></div>

            <div class="relative bg-panel rounded-2xl w-full max-w-3xl shadow-modal flex flex-col max-h-full">
                <header class="px-5 py-3.5 border-b border-line flex items-center justify-between gap-4 shrink-0">
                    <div>
                        <h3 class="text-[14px] font-semibold text-ink">Pick the location</h3>
                        <p class="text-[11.5px] text-ink-3 mt-0.5">
                            Search a pincode or place, or click the map to drop a pin.
                        </p>
                    </div>
                    <button type="button" @click="closeMap()" aria-label="Close map"
                            class="text-ink-3 hover:text-ink hover:bg-canvas rounded-lg p-1.5 transition-colors">
                        <x-icon name="x" class="w-4 h-4" />
                    </button>
                </header>

                <div class="px-5 py-3 border-b border-line-soft shrink-0">
                    <div class="flex items-center gap-2">
                        <div class="relative flex-1">
                            <x-icon name="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-ink-3 pointer-events-none" />
                            <input type="search" x-model="query" @keydown.enter.prevent="search()"
                                   placeholder="e.g. 500032, or Gachibowli Hyderabad"
                                   class="w-full h-9 pl-9 pr-3 rounded-lg bg-panel border border-line text-[13px] text-ink
                                          placeholder:text-ink-3 focus:outline-none focus:border-primary
                                          focus:ring-[3px] focus:ring-primary-ring transition-[border-color,box-shadow]">
                        </div>
                        <x-button variant="subtle" tag="button" type="button" size="sm" x-on:click="search()"
                                  x-bind:disabled="busy || query.trim().length < 3">
                            <span x-show="! busy">Search</span>
                            <span x-show="busy" x-cloak>…</span>
                        </x-button>
                    </div>

                    <ul x-show="results.length" x-cloak
                        class="mt-2 max-h-40 overflow-y-auto scrollbar-slim divide-y divide-line-soft rounded-lg ring-1 ring-inset ring-line">
                        <template x-for="hit in results" :key="hit.label">
                            <li>
                                <button type="button" @click="selectResult(hit)"
                                        class="w-full text-left px-3 py-2 hover:bg-canvas transition-colors">
                                    <span class="block text-[12.5px] text-ink" x-text="hit.address"></span>
                                </button>
                            </li>
                        </template>
                    </ul>
                </div>

                <div x-ref="map" class="w-full h-[340px] min-h-[340px] bg-canvas shrink-0"></div>

                <footer class="px-5 py-3 border-t border-line flex items-center justify-between gap-4 shrink-0">
                    <div class="min-w-0 flex-1">
                        <p x-show="error" x-cloak x-text="error" class="text-[11.5px] text-danger"></p>
                        <p x-show="! error && picked" x-cloak class="text-[11.5px] text-ink-2 truncate"
                           x-text="picked ? picked.address : ''"></p>
                        <p x-show="! error && ! picked" x-cloak class="text-[11.5px] text-ink-3">
                            Nothing selected yet.
                        </p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <x-button variant="subtle" tag="button" type="button" size="sm" x-on:click="closeMap()">
                            Cancel
                        </x-button>
                        <x-button variant="primary" tag="button" type="button" size="sm"
                                  x-on:click="apply()" x-bind:disabled="! picked">
                            Use this location
                        </x-button>
                    </div>
                </footer>
            </div>
        </div>
    </template>
</div>
