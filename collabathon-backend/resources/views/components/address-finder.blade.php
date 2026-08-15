@props([
    'address' => null,
    'pincode' => null,
    'latitude' => null,
    'longitude' => null,
])

@php
/**
 * One visible field — the address — plus a link that opens a map to fill it.
 *
 * Pincode, latitude and longitude are hidden inputs. Nobody types coordinates, and a
 * pincode is something the lookup already knows; but all three are worth storing, because
 * they are what makes the address usable later for a map link or a distance query. The
 * map fills them and the admin never sees them.
 *
 * The textarea stays fully editable: the map is a shortcut, not a gate. A developer at a
 * plot number no geocoder has heard of must still be enterable by hand.
 */
$currentAddress = old('address', $address);
$currentPincode = old('pincode', $pincode);
$currentLat = old('latitude', $latitude);
$currentLng = old('longitude', $longitude);
@endphp

<div x-data="addressFinder({
        endpoint: @js(route('admin.geocode')),
        pincode: @js($currentPincode ?? ''),
        latitude: @js($currentLat ?? ''),
        longitude: @js($currentLng ?? ''),
     })"
     @keydown.escape.window="open && closeMap()">

    <label for="address" class="flex items-center gap-1 text-[12.5px] font-medium text-ink mb-1.5">
        Address
    </label>

    <textarea id="address" name="address" rows="2" x-ref="address"
              placeholder="Building, street, locality…"
              class="w-full rounded-lg bg-panel border py-2.5 px-3.5 text-[13.5px] text-ink placeholder:text-ink-3
                     focus:outline-none focus:ring-[3px] transition-[border-color,box-shadow] resize-y
                     {{ $errors->has('address')
                        ? 'border-danger focus:border-danger focus:ring-danger-ring'
                        : 'border-line focus:border-primary focus:ring-primary-ring' }}">{{ $currentAddress }}</textarea>

    @error('address')
        <p class="flex items-start gap-1.5 text-[11.5px] text-danger mt-1.5">
            <x-icon name="x" class="w-3.5 h-3.5 shrink-0 mt-px" /><span>{{ $message }}</span>
        </p>
    @enderror

    <button type="button" @click="openMap()"
            class="inline-flex items-center gap-1.5 text-[12px] text-primary-dark hover:underline mt-2">
        <x-icon name="map-pin" class="w-3.5 h-3.5" />
        Fetch from map
    </button>

    <p x-show="pincode || latitude" x-cloak class="text-[11px] text-ink-3 mt-1.5 nums">
        Captured <span x-text="pincode ? 'pincode ' + pincode : ''"></span><span
            x-show="pincode && latitude">, </span><span
            x-text="latitude ? Number(latitude).toFixed(5) + ', ' + Number(longitude).toFixed(5) : ''"></span>
    </p>

    {{-- Filled by the map, never typed. --}}
    <input type="hidden" name="pincode" :value="pincode">
    <input type="hidden" name="latitude" :value="latitude">
    <input type="hidden" name="longitude" :value="longitude">

    {{-- ------------------------------------------------------------------ map modal
         Teleported to body: this component renders inside the developer form, which is
         itself inside a modal, and a nested absolutely-positioned overlay would be
         clipped by that dialog's own stacking context. --}}
    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center px-4 py-8"
             role="dialog" aria-modal="true" aria-label="Pick an address on the map">

            <div class="fixed inset-0 bg-scrim" @click="closeMap()"></div>

            <div class="relative bg-panel rounded-2xl w-full max-w-3xl shadow-modal flex flex-col max-h-full overflow-hidden">
                {{-- overflow-hidden — see the matching note in location-finder.blade.php:
                     Leaflet's own zoom/attribution controls carry z-index:1000 and are not
                     otherwise clipped to the map's own box, so without this they can sit on
                     top of (and swallow clicks meant for) this card's header/footer. --}}
                <header class="px-5 py-3.5 border-b border-line flex items-center justify-between gap-4 shrink-0">
                    <div>
                        <h3 class="text-[14px] font-semibold text-ink">Pick the address</h3>
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
                                   placeholder="e.g. 560085, or Gachibowli Hyderabad"
                                   class="w-full h-9 pl-9 pr-3 rounded-lg bg-panel border border-line shadow-card text-[13px] text-ink
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

                {{-- min-h, not just h: Leaflet needs a measurable box or it renders blank. --}}
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
                            Use this address
                        </x-button>
                    </div>
                </footer>
            </div>
        </div>
    </template>
</div>
