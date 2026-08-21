/**
 * Client-side image compression for upload-heavy admin forms.
 *
 * The project intake form can carry a cover image, a 30-shot gallery, unit layouts and a
 * floor plan per unit type. Straight off a phone that is comfortably past any sane
 * `post_max_size`, and the failure mode is a raw 413 with the whole form lost. Re-encoding
 * in the browser turns a 5 MB photo into roughly 300–500 KB before it ever leaves the page,
 * which also keeps listing pages fast for brokers.
 *
 * Deliberately conservative: anything that cannot be decoded, is already small, or would
 * come out bigger is passed through untouched. Compression is an optimisation, never a
 * gate — a file that resists it still uploads as-is and the server decides.
 */

const MAX_EDGE = 1920;          // longest side, px — ample for a full-bleed hero image
const QUALITY = 0.82;           // JPEG quality; visually clean, ~10x smaller than source
const SIZE_FLOOR = 320 * 1024;  // below this, re-encoding costs more than it saves

// SVG is vector (rasterising it is a downgrade) and GIF may be animated (canvas keeps
// only the first frame). Neither is what an admin means by "compress".
const PASS_THROUGH = ['image/svg+xml', 'image/gif'];

/** @returns {Promise<File>} the re-encoded file, or the original if it should not change. */
async function compressImage(file) {
    if (! file.type.startsWith('image/')) return file;
    if (PASS_THROUGH.includes(file.type)) return file;
    if (file.size <= SIZE_FLOOR) return file;

    try {
        // `from-image` applies EXIF orientation — without it, phone photos land rotated.
        const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });

        const scale = Math.min(1, MAX_EDGE / Math.max(bitmap.width, bitmap.height));
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(bitmap.width * scale);
        canvas.height = Math.round(bitmap.height * scale);

        const context = canvas.getContext('2d');
        context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        bitmap.close?.();

        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', QUALITY));

        // A small or already-optimised source can re-encode larger. Keep the smaller one.
        if (! blob || blob.size >= file.size) return file;

        const name = file.name.replace(/\.[^.]+$/, '') + '.jpg';

        return new File([blob], name, { type: 'image/jpeg', lastModified: file.lastModified });
    } catch {
        // Formats the browser cannot decode (HEIC is the common one) land here.
        return file;
    }
}

/**
 * Hard ceiling on a whole compression pass.
 *
 * createImageBitmap and canvas.toBlob are the browser's to schedule, and a decode that
 * never settles would leave a form waiting forever on a promise that never resolves.
 */
const OVERALL_TIMEOUT = 20000;

/**
 * Re-encode every image across a form's file inputs, in place.
 *
 * Resolves with zeroed totals if the pass outruns OVERALL_TIMEOUT — callers read that as
 * "compression did not happen" and upload the originals, which the server validates
 * anyway. Compression must never be the reason a form cannot be submitted.
 *
 * @param  {HTMLFormElement} form
 * @returns {Promise<{before: number, after: number, timedOut?: boolean}>} upload bytes, pre and post
 */
export async function compressFileInputs(form) {
    return Promise.race([
        reencodeFileInputs(form),
        new Promise((resolve) => setTimeout(
            () => resolve({ before: 0, after: 0, timedOut: true }),
            OVERALL_TIMEOUT,
        )),
    ]);
}

/**
 * A FileList is read-only, so the replacement is assembled through DataTransfer — the only
 * way to write back to an <input type="file"> without dropping the user's selection.
 */
async function reencodeFileInputs(form) {
    let before = 0;
    let after = 0;

    for (const input of form.querySelectorAll('input[type="file"]')) {
        if (! input.files?.length) continue;

        const originals = Array.from(input.files);
        const compressed = await Promise.all(originals.map(compressImage));

        originals.forEach((file) => { before += file.size });
        compressed.forEach((file) => { after += file.size });

        // Nothing changed — leave the original FileList alone rather than rebuilding it.
        if (compressed.every((file, index) => file === originals[index])) continue;

        const transfer = new DataTransfer();
        compressed.forEach((file) => transfer.items.add(file));
        input.files = transfer.files;
    }

    return { before, after };
}

/**
 * A temporary password an admin can read aloud or paste into WhatsApp.
 *
 * Symbols are omitted (they get mangled by chat clients and shells) along with the
 * 0/O and 1/l/I look-alikes, so the string survives being dictated over a phone call.
 * crypto.getRandomValues, not Math.random — this becomes a real account credential.
 */
export function makePassword(length = 14) {
    const alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';

    return Array.from(crypto.getRandomValues(new Uint32Array(length)))
        .map((n) => alphabet[n % alphabet.length])
        .join('');
}

/* ------------------------------------------------------------------ address picker */

// Hyderabad. Only ever seen when a developer has no coordinates yet and the admin opens
// the map before searching — an arbitrary centre is better than a view of the null island.
const FALLBACK_CENTRE = [17.4401, 78.3489];

let googleMapsPromise = null;

/**
 * Loads the Google Maps JavaScript API the first time either map picker is opened, not on
 * page load — most admin pages never show one, so most page loads pay nothing for it.
 * The promise is cached so opening a second map (or the same one again) is instant and
 * never injects the `<script>` tag twice, which the API itself does not tolerate cleanly.
 *
 * The key comes from `window.GOOGLE_MAPS_API_KEY`, set once in layouts/admin.blade.php
 * from `config('services.google_maps.key')` — never hardcoded here, so it stays in one
 * place (the `.env`) to rotate.
 */
function loadGoogleMaps() {
    if (googleMapsPromise) return googleMapsPromise;

    googleMapsPromise = new Promise((resolve, reject) => {
        if (window.google?.maps) {
            resolve(window.google.maps);
            return;
        }

        const key = window.GOOGLE_MAPS_API_KEY;
        if (!key) {
            reject(new Error('Google Maps API key is not configured.'));
            return;
        }

        // The API's own loader calls a *global* function by name — it cannot call back
        // into this closure directly — so the callback is hung off `window` under a name
        // unlikely to collide with anything else on the page.
        window.__collabathonGoogleMapsReady = () => resolve(window.google.maps);

        const script = document.createElement('script');
        script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(key)}&callback=__collabathonGoogleMapsReady&loading=async`;
        script.async = true;
        script.onerror = () => reject(new Error('Google Maps failed to load'));
        document.head.appendChild(script);
    });

    return googleMapsPromise;
}

/**
 * The developer form's address field: one textarea, plus a map for picking it.
 *
 * The map is a modal rather than an inline panel because the form is already inside one —
 * an embedded map would have to compete for the same vertical space as every other field,
 * and a map you cannot pan comfortably is worse than no map.
 *
 * Nothing is written back to the form until "Use this address" is pressed. Panning and
 * clicking around is exploratory; committing is a separate, deliberate act.
 */
export function addressFinder(config = {}) {
    return {
        endpoint: config.endpoint,
        pincode: config.pincode ?? '',
        latitude: config.latitude ?? '',
        longitude: config.longitude ?? '',

        open: false,
        query: '',
        results: [],
        busy: false,
        error: '',
        picked: null,
        map: null,
        marker: null,

        async openMap() {
            this.open = true;
            this.error = '';

            let maps;
            try {
                maps = await loadGoogleMaps();
            } catch {
                this.error = 'The map could not be loaded. Type the address instead.';
                return;
            }

            // The map measures its container on init. Inside a modal that was
            // display:none a moment ago that measurement is 0x0, so the map is built
            // after the DOM has settled and then explicitly told to re-measure below.
            await this.$nextTick();

            const centre = this.latitude && this.longitude
                ? { lat: Number(this.latitude), lng: Number(this.longitude) }
                : { lat: FALLBACK_CENTRE[0], lng: FALLBACK_CENTRE[1] };

            if (! this.map) {
                this.map = new maps.Map(this.$refs.map, {
                    center: centre,
                    zoom: this.latitude ? 16 : 11,
                    mapTypeControl: false,
                    streetViewControl: false,
                    fullscreenControl: false,
                });

                this.map.addListener('click', (event) => this.dropPin(event.latLng.lat(), event.latLng.lng()));
            } else {
                this.map.setCenter(centre);
                this.map.setZoom(this.latitude ? 16 : 11);
            }

            setTimeout(() => maps.event.trigger(this.map, 'resize'), 60);

            if (this.latitude && this.longitude) {
                this.setMarker(Number(this.latitude), Number(this.longitude));
            }
        },

        closeMap() {
            this.open = false;
            this.results = [];
            this.error = '';
        },

        setMarker(lat, lng) {
            const maps = window.google?.maps;
            if (! maps || ! this.map) return;

            if (this.marker) {
                this.marker.setPosition({ lat, lng });
            } else {
                this.marker = new maps.Marker({ position: { lat, lng }, map: this.map, draggable: true });
                this.marker.addListener('dragend', () => {
                    const p = this.marker.getPosition();
                    this.dropPin(p.lat(), p.lng());
                });
            }
        },

        /** Map click or pin drag: resolve those coordinates to an address. */
        async dropPin(lat, lng) {
            this.setMarker(lat, lng);
            this.busy = true;
            this.error = '';

            try {
                const res = await fetch(`${this.endpoint}?lat=${lat}&lon=${lng}`, {
                    headers: { Accept: 'application/json' },
                });
                const hits = (await res.json()).data ?? [];
                this.picked = hits[0] ?? null;
                if (! this.picked) this.error = 'No address at that point. Try somewhere nearer a road.';
            } catch {
                this.error = 'Could not reach the address service.';
            } finally {
                this.busy = false;
            }
        },

        /** Search box inside the modal: pincode or place name. */
        async search() {
            if (this.query.trim().length < 3) return;
            this.busy = true;
            this.error = '';
            this.results = [];

            try {
                const res = await fetch(`${this.endpoint}?q=${encodeURIComponent(this.query.trim())}`, {
                    headers: { Accept: 'application/json' },
                });
                this.results = (await res.json()).data ?? [];
                if (! this.results.length) this.error = 'Nothing found for that.';
            } catch {
                this.error = 'Could not reach the address service.';
            } finally {
                this.busy = false;
            }
        },

        /** A search result was clicked: centre the map on it and make it the candidate. */
        selectResult(hit) {
            this.picked = hit;
            this.results = [];
            this.query = '';

            if (this.map && hit.latitude && hit.longitude) {
                this.map.setView([hit.latitude, hit.longitude], 16);
                this.setMarker(hit.latitude, hit.longitude);
            }
        },

        /** Commit the candidate to the form. */
        apply() {
            if (! this.picked) return;

            this.$refs.address.value = this.picked.address;
            this.pincode = this.picked.pincode ?? '';
            this.latitude = this.picked.latitude ?? '';
            this.longitude = this.picked.longitude ?? '';
            this.picked = null;
            this.closeMap();
        },
    };
}

/**
 * The project form's coordinate picker: same search-or-click map as {@see addressFinder},
 * but committing writes into the form's own existing latitude/longitude/pincode/maps-link
 * fields (via $refs) instead of a single address textarea — the project form already
 * splits the address into locality/landmark/full address, and this only owns the three
 * fields nobody can type accurately by hand: the coordinates and the map link.
 */
export function locationFinder(config = {}) {
    return {
        endpoint: config.endpoint,
        latitude: config.latitude ?? '',
        longitude: config.longitude ?? '',

        open: false,
        query: '',
        results: [],
        busy: false,
        error: '',
        picked: null,
        map: null,
        marker: null,

        async openMap() {
            this.open = true;
            this.error = '';

            let maps;
            try {
                maps = await loadGoogleMaps();
            } catch {
                this.error = 'The map could not be loaded. Enter the coordinates by hand instead.';
                return;
            }

            await this.$nextTick();

            const centre = this.latitude && this.longitude
                ? { lat: Number(this.latitude), lng: Number(this.longitude) }
                : { lat: FALLBACK_CENTRE[0], lng: FALLBACK_CENTRE[1] };

            if (! this.map) {
                this.map = new maps.Map(this.$refs.map, {
                    center: centre,
                    zoom: this.latitude ? 16 : 11,
                    mapTypeControl: false,
                    streetViewControl: false,
                    fullscreenControl: false,
                });

                this.map.addListener('click', (event) => this.dropPin(event.latLng.lat(), event.latLng.lng()));
            } else {
                this.map.setCenter(centre);
                this.map.setZoom(this.latitude ? 16 : 11);
            }

            setTimeout(() => maps.event.trigger(this.map, 'resize'), 60);

            if (this.latitude && this.longitude) {
                this.setMarker(Number(this.latitude), Number(this.longitude));
            }
        },

        closeMap() {
            this.open = false;
            this.results = [];
            this.error = '';
        },

        setMarker(lat, lng) {
            const maps = window.google?.maps;
            if (! maps || ! this.map) return;

            if (this.marker) {
                this.marker.setPosition({ lat, lng });
            } else {
                this.marker = new maps.Marker({ position: { lat, lng }, map: this.map, draggable: true });
                this.marker.addListener('dragend', () => {
                    const p = this.marker.getPosition();
                    this.dropPin(p.lat(), p.lng());
                });
            }
        },

        async dropPin(lat, lng) {
            this.setMarker(lat, lng);
            this.busy = true;
            this.error = '';

            try {
                const res = await fetch(`${this.endpoint}?lat=${lat}&lon=${lng}`, {
                    headers: { Accept: 'application/json' },
                });
                const hits = (await res.json()).data ?? [];
                this.picked = hits[0] ?? null;
                if (! this.picked) this.error = 'No address at that point. Try somewhere nearer a road.';
            } catch {
                this.error = 'Could not reach the address service.';
            } finally {
                this.busy = false;
            }
        },

        async search() {
            if (this.query.trim().length < 3) return;
            this.busy = true;
            this.error = '';
            this.results = [];

            try {
                const res = await fetch(`${this.endpoint}?q=${encodeURIComponent(this.query.trim())}`, {
                    headers: { Accept: 'application/json' },
                });
                this.results = (await res.json()).data ?? [];
                if (! this.results.length) this.error = 'Nothing found for that.';
            } catch {
                this.error = 'Could not reach the address service.';
            } finally {
                this.busy = false;
            }
        },

        selectResult(hit) {
            this.picked = hit;
            this.results = [];
            this.query = '';

            if (this.map && hit.latitude && hit.longitude) {
                this.map.setView([hit.latitude, hit.longitude], 16);
                this.setMarker(hit.latitude, hit.longitude);
            }
        },

        /**
         * Commit the candidate into the form's own fields. Pincode is filled only when
         * the admin has not already typed one — the project form's pincode is one of
         * several address fields entered by hand elsewhere on this same step, unlike the
         * developer form where the whole address (pincode included) comes from the map.
         */
        apply() {
            if (! this.picked) return;

            this.latitude = this.picked.latitude ?? '';
            this.longitude = this.picked.longitude ?? '';

            if (this.$refs.latitude) this.$refs.latitude.value = this.latitude;
            if (this.$refs.longitude) this.$refs.longitude.value = this.longitude;

            if (this.$refs.pincode && this.picked.pincode && ! this.$refs.pincode.value.trim()) {
                this.$refs.pincode.value = this.picked.pincode;
            }

            if (this.$refs.mapsLink && this.latitude && this.longitude) {
                this.$refs.mapsLink.value = `https://www.google.com/maps?q=${this.latitude},${this.longitude}`;
            }

            this.picked = null;
            this.closeMap();
        },
    };
}

/**
 * "Fetch nearby places": reads whatever latitude/longitude are currently in the form
 * (typed by hand or filled by locationFinder — either is fine, this does not care which)
 * and turns them into a first draft of the Connectivity highlights and Nearby social
 * infrastructure fields.
 *
 * Deliberately its own component rather than folded into locationFinder: those
 * coordinate fields sit in one grid, these text fields sit in another, and the two
 * actions are independent — an admin can re-fetch nearby places after nudging the pin
 * without reopening the map, or fetch once and then edit the coordinates by hand.
 */
export function nearbyPlacesFinder(config = {}) {
    return {
        endpoint: config.endpoint,
        busy: false,
        error: '',

        async fetchNearby() {
            const form = this.$refs.connectivity?.closest('form');
            const lat = form?.querySelector('[name="latitude"]')?.value;
            const lon = form?.querySelector('[name="longitude"]')?.value;

            if (!lat || !lon) {
                this.error = 'Set the location (latitude/longitude) above first.';
                return;
            }

            this.busy = true;
            this.error = '';

            try {
                const res = await fetch(`${this.endpoint}?lat=${lat}&lon=${lon}`, {
                    headers: { Accept: 'application/json' },
                });
                const data = await res.json();

                if (!data.reached) {
                    this.error = 'Could not reach the places service. Try again shortly.';
                    return;
                }

                if (!data.connectivity?.length && !data.social?.length) {
                    this.error = 'Nothing found nearby — the area may be too sparsely mapped.';
                    return;
                }

                // Appended, not replaced: an admin may have already typed something
                // worth keeping, and a blank line separates their text from the fetch.
                if (data.connectivity?.length && this.$refs.connectivity) {
                    this.$refs.connectivity.value = [this.$refs.connectivity.value.trim(), data.connectivity.join('\n')]
                        .filter(Boolean).join('\n');
                }
                if (data.social?.length && this.$refs.social) {
                    this.$refs.social.value = [this.$refs.social.value.trim(), data.social.join('\n')]
                        .filter(Boolean).join('\n');
                }
            } catch {
                this.error = 'Could not reach the places service.';
            } finally {
                this.busy = false;
            }
        },
    };
}

/**
 * Interactive crop for logo uploads — a fixed 5:2 ratio (a logo is a wide wordmark, not a
 * square headshot), drag to reposition and a slider to zoom, canvas-based like
 * compressImage above rather than a runtime cropping library (this project has none, and
 * deliberately hand-rolls its own image handling — see the file header).
 *
 * The real <input type="file"> stays empty until apply() runs: a picked file only ever
 * produces a bitmap held in memory here, never sits in the input's own FileList directly.
 * A raw, uncropped file can never reach the form. compressFileInputs() still runs at
 * submit time same as any other file input — it operates generically over
 * `input[type="file"]` with no knowledge of "crop," so the cropped PNG this produces just
 * gets whatever further re-encoding its own size warrants, same as any upload.
 */
const LOGO_RATIO = 5 / 2;
const LOGO_OUTPUT_WIDTH = 600;
const LOGO_OUTPUT_HEIGHT = Math.round(LOGO_OUTPUT_WIDTH / LOGO_RATIO);
const LOGO_FRAME_WIDTH = 320;
const LOGO_FRAME_HEIGHT = Math.round(LOGO_FRAME_WIDTH / LOGO_RATIO);

export function cropTool() {
    return {
        frameWidth: LOGO_FRAME_WIDTH,
        frameHeight: LOGO_FRAME_HEIGHT,

        editing: false,
        fileName: '',
        previewUrl: null,
        bitmap: null,
        scale: 1,
        minScale: 1,
        offsetX: 0,
        offsetY: 0,
        dragging: false,
        dragStartX: 0,
        dragStartY: 0,
        dragOriginX: 0,
        dragOriginY: 0,

        async onFileChange(event) {
            const file = event.target.files?.[0];
            if (! file) return;

            this.fileName = file.name;

            let bitmap;
            try {
                // `from-image` applies EXIF orientation — without it, phone photos land rotated.
                bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
            } catch {
                // Not decodable in-browser (e.g. HEIC) — nothing to crop against. Leave the
                // raw file sitting in the input as picked; the server still validates it.
                return;
            }

            this.bitmap?.close?.();
            this.bitmap = bitmap;

            // The smallest scale at which the image still fully covers the frame on both
            // axes — below this, the frame would show empty space at an edge.
            this.minScale = Math.max(this.frameWidth / bitmap.width, this.frameHeight / bitmap.height);
            this.scale = this.minScale;
            this.offsetX = 0;
            this.offsetY = 0;
            this.editing = true;

            await this.$nextTick();
            this.draw();
        },

        startDrag(event) {
            this.dragging = true;
            this.dragStartX = event.clientX;
            this.dragStartY = event.clientY;
            this.dragOriginX = this.offsetX;
            this.dragOriginY = this.offsetY;
        },

        onDrag(event) {
            if (! this.dragging) return;
            this.offsetX = this.dragOriginX + (event.clientX - this.dragStartX);
            this.offsetY = this.dragOriginY + (event.clientY - this.dragStartY);
            this.clampOffset();
            this.draw();
        },

        endDrag() {
            this.dragging = false;
        },

        onZoom(value) {
            this.scale = Number(value);
            this.clampOffset();
            this.draw();
        },

        /** Keeps the frame fully covered — the image can be panned, never past its own edge. */
        clampOffset() {
            const width = this.bitmap.width * this.scale;
            const height = this.bitmap.height * this.scale;
            const maxX = Math.max(0, (width - this.frameWidth) / 2);
            const maxY = Math.max(0, (height - this.frameHeight) / 2);
            this.offsetX = Math.min(maxX, Math.max(-maxX, this.offsetX));
            this.offsetY = Math.min(maxY, Math.max(-maxY, this.offsetY));
        },

        draw() {
            const canvas = this.$refs.canvas;
            if (! canvas || ! this.bitmap) return;

            canvas.width = this.frameWidth;
            canvas.height = this.frameHeight;

            const context = canvas.getContext('2d');
            context.clearRect(0, 0, this.frameWidth, this.frameHeight);

            const width = this.bitmap.width * this.scale;
            const height = this.bitmap.height * this.scale;
            const x = (this.frameWidth - width) / 2 + this.offsetX;
            const y = (this.frameHeight - height) / 2 + this.offsetY;
            context.drawImage(this.bitmap, x, y, width, height);
        },

        async apply() {
            if (! this.bitmap) return;

            const canvas = document.createElement('canvas');
            canvas.width = LOGO_OUTPUT_WIDTH;
            canvas.height = LOGO_OUTPUT_HEIGHT;

            // Same crop, rendered at the real output resolution rather than the (smaller)
            // on-screen preview size.
            const outputScale = LOGO_OUTPUT_WIDTH / this.frameWidth;
            const width = this.bitmap.width * this.scale * outputScale;
            const height = this.bitmap.height * this.scale * outputScale;
            const x = (LOGO_OUTPUT_WIDTH - width) / 2 + this.offsetX * outputScale;
            const y = (LOGO_OUTPUT_HEIGHT - height) / 2 + this.offsetY * outputScale;

            const context = canvas.getContext('2d');
            context.drawImage(this.bitmap, x, y, width, height);

            // PNG, not JPEG — preserves transparency for a wordmark logo on a transparent
            // background. compressFileInputs() still flattens/re-encodes this later, but
            // only if its own size actually warrants it.
            const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
            if (! blob) return;

            const name = this.fileName.replace(/\.[^.]+$/, '') + '-cropped.png';
            const file = new File([blob], name, { type: 'image/png' });

            // Same DataTransfer technique compressFileInputs uses — FileList is read-only.
            const transfer = new DataTransfer();
            transfer.items.add(file);
            this.$refs.input.files = transfer.files;

            if (this.previewUrl) URL.revokeObjectURL(this.previewUrl);
            this.previewUrl = URL.createObjectURL(blob);

            this.editing = false;
            this.bitmap.close?.();
            this.bitmap = null;
        },

        cancel() {
            this.editing = false;
            this.bitmap?.close?.();
            this.bitmap = null;
            this.fileName = '';
            this.$refs.input.value = '';
        },
    };
}

// Alpine loads from a CDN and evaluates its expressions from the global scope, so the
// wizard and the credential dialogs reach these through `window` rather than an import.
window.compressFileInputs = compressFileInputs;
window.makePassword = makePassword;
window.addressFinder = addressFinder;
window.locationFinder = locationFinder;
window.nearbyPlacesFinder = nearbyPlacesFinder;
window.cropTool = cropTool;

/* ------------------------------------------------------------------ dashboard KPI panel */

/**
 * The dashboard's six KPI tiles open a filtered/searchable/paginated row list right
 * below the cards. Every link/form involved — a tile, and everything the panel itself
 * renders (search, filters, sort headers, pagination, close) — is a plain link to
 * `/admin/dashboard` or `/admin/dashboard/panel`, so the feature is a real, working
 * page with JavaScript off. This layer intercepts only those same-page requests and
 * swaps `#panel-wrap`'s contents in place instead of letting the browser navigate —
 * opening, searching or paging a panel no longer reloads the whole dashboard. A row's
 * own link (developer/project/broker/lead) and "Open full page" point elsewhere and
 * are deliberately left alone, so those still leave the page normally.
 *
 * `history.pushState` keeps the address bar matching what's on screen, for deep links
 * and refresh. Back/forward just reloads — correctness over animating a rare path.
 */
(() => {
    const DASHBOARD_PATH = '/admin/dashboard';

    const panelWrap = () => document.getElementById('panel-wrap');

    const isDashboardUrl = (href) => {
        try {
            return new URL(href, window.location.origin).pathname.startsWith(DASHBOARD_PATH);
        } catch {
            return false;
        }
    };

    // A dashboard-shaped href is only actually *panel* traffic if it opens/switches a
    // tile (`?panel=…`, e.g. a KPI tile or the panel's own search/sort/pagination — all
    // built with `withQueryString()`, so they keep `panel=`) or belongs to the panel's
    // own chrome (its "Close" button, which deliberately points at the bare
    // `/admin/dashboard` with no `panel=` so the page still works with JS off — that
    // link is only distinguishable by living inside #panel-wrap). The sidebar's
    // persistent "Dashboard" link is the *same* bare URL as that Close button but lives
    // outside #panel-wrap, in the nav — without this containment check it was
    // indistinguishable from Close and got the same in-place, scroll-preserving swap
    // instead of the real navigation (and scroll-to-top) re-clicking "Dashboard" should do.
    const isPanelLink = (link) => {
        if (!isDashboardUrl(link.href)) return false;

        try {
            if (new URL(link.href, window.location.origin).searchParams.has('panel')) return true;
        } catch {
            return false;
        }

        return panelWrap()?.contains(link) ?? false;
    };

    // The fragment route is always `.../dashboard/panel` with the same query string —
    // whichever of the two shapes a link already carries, this normalises to that one.
    const fragmentUrl = (href) => {
        const url = new URL(href, window.location.origin);
        if (!url.pathname.endsWith('/panel')) {
            url.pathname = url.pathname.replace(/\/dashboard\/?$/, '/dashboard/panel');
        }
        return url;
    };

    // The inverse, for the address bar — a fetch always goes to .../panel, but the
    // pretty URL a person sees (and can reload or share) stays plain /dashboard.
    const displayUrl = (href) => {
        const url = new URL(href, window.location.origin);
        url.pathname = url.pathname.replace(/\/dashboard\/panel\/?$/, '/dashboard');
        return url;
    };

    const syncActiveTile = (openKey) => {
        document.querySelectorAll('[data-kpi-tile]').forEach((tile) => {
            const active = tile.dataset.kpiTile === openKey;
            tile.classList.toggle('border-primary-ring', active);
            tile.classList.toggle('shadow-md', active);
        });
    };

    // The layout's <body x-data="{ navigating: false }"> shows a full-page skeleton on
    // every link click (see admin.blade.php) and only clears it once the browser
    // finishes navigating. Since this panel never actually navigates, that flag has to
    // be put back by hand or the skeleton is stuck on screen forever over a panel that
    // in fact loaded fine underneath it.
    const clearNavigatingSkeleton = () => {
        const data = window.Alpine?.$data?.(document.body);
        if (data) data.navigating = false;
    };

    let inFlight = null;

    async function loadPanel(href) {
        const target = panelWrap();
        if (!target) return;

        clearNavigatingSkeleton();

        inFlight?.abort();
        inFlight = new AbortController();

        target.classList.add('opacity-60', 'pointer-events-none', 'transition-opacity', 'duration-150');
        if (!target.innerHTML.trim()) {
            target.innerHTML = '<div class="py-16 text-center text-[13px] text-ink-3">Loading…</div>';
        }

        try {
            const response = await fetch(fragmentUrl(href), {
                headers: {'X-Requested-With': 'XMLHttpRequest'},
                signal: inFlight.signal,
            });

            target.innerHTML = await response.text();
            target.classList.remove('opacity-60', 'pointer-events-none');

            const shown = displayUrl(href);
            history.pushState({panel: shown.href}, '', shown.href);
            syncActiveTile(shown.searchParams.get('panel'));

            target.querySelector('#panel')?.scrollIntoView({behavior: 'smooth', block: 'start'});
        } catch (error) {
            if (error.name === 'AbortError') return;

            // The href underneath still works — a real navigation beats a panel that
            // silently never updates.
            window.location.href = href;
        }
    }

    // Capture phase, and deliberately registered on `document` rather than a nearer
    // ancestor: capture listeners fire root-to-target, so this runs — and calls
    // preventDefault() — before admin.blade.php's own `x-on:click.capture` on <body>
    // gets to check `$event.defaultPrevented` and (wrongly) flag a real navigation.
    document.addEventListener('click', (event) => {
        // Off the dashboard page entirely, e.g. the sidebar's own "Dashboard" link
        // clicked from elsewhere: let it navigate there normally.
        if (!panelWrap()) return;

        const link = event.target.closest('a[href]');
        if (!link || !isPanelLink(link)) return;

        // Not every dashboard-shaped link is a panel to fetch. The export menu's two
        // items point at this same /admin/dashboard path with `?export=` on it, and
        // fetching those swallowed the response into the panel area instead of letting
        // the browser download the CSV / open the print view — the export looked dead.
        // `download` and `target=_blank` are precisely the two markers for that, and are
        // the same pair admin.blade.php's own click handler skips.
        if (link.hasAttribute('download') || link.target === '_blank') return;

        // A modifier click means "open this somewhere else", which a fetch cannot honour.
        if (event.defaultPrevented || event.metaKey || event.ctrlKey
            || event.shiftKey || event.altKey || event.button !== 0) return;

        event.preventDefault();
        loadPanel(link.href);
    }, true);

    document.addEventListener('submit', (event) => {
        const form = event.target.closest('#panel-wrap form');
        if (!form) return;

        event.preventDefault();
        const url = new URL(form.getAttribute('action') || window.location.href, window.location.origin);
        url.search = new URLSearchParams(new FormData(form)).toString();
        loadPanel(url.href);
    }, true);

    window.addEventListener('popstate', () => window.location.reload());
})();

/* ------------------------------------------------------------------ generic AJAX list panel */

/**
 * Any element marked `data-ajax-panel` refreshes its own contents in place instead of
 * the browser navigating, for the links and GET forms inside it — pagination and a
 * per-page selector, the two things a plain paginated table needs (see Team's own
 * `data-ajax-panel` on its table partial, and TeamController::index()'s ajax()/view()
 * fork, for the first page wired up this way).
 *
 * Modelled directly on the dashboard KPI panel above — same capture-phase-on-document
 * technique, same clearNavigatingSkeleton, same "the href underneath still works, so
 * fall back to a real navigation on failure" — generalised to any container via a data
 * attribute instead of one hardcoded to /admin/dashboard, since Team is the first page
 * that needs this shape but is very unlikely to be the last.
 *
 * Deliberately GET-only: a paginated table's row actions (Team's edit/delete/role
 * change, say) are unrelated to why the screen used to reload — that was pagination
 * and per-page alone, and each action already redirects back to a correct, working
 * page today. Bringing those under this same fetch+swap contract would mean every one
 * of them answering with JSON instead of a redirect (see settingsResponse() below for
 * what that looks like) for no reason relevant to this problem — left alone here.
 */
(() => {
    const panelOf = (el) => el.closest('[data-ajax-panel]');

    // Same reasoning as the dashboard panel's own copy of this: the layout's
    // <body x-data="{ navigating: false }"> flags a full-page skeleton on every
    // click/submit and only clears it once the browser actually navigates. This
    // never navigates, so the flag has to be put back by hand.
    const clearNavigatingSkeleton = () => {
        const data = window.Alpine?.$data?.(document.body);
        if (data) data.navigating = false;
    };

    let inFlight = null;

    async function loadInto(panel, href) {
        clearNavigatingSkeleton();

        inFlight?.abort();
        inFlight = new AbortController();

        panel.classList.add('opacity-60', 'pointer-events-none', 'transition-opacity', 'duration-150');

        try {
            const response = await fetch(href, {
                headers: {'X-Requested-With': 'XMLHttpRequest'},
                signal: inFlight.signal,
            });
            const html = await response.text();

            // outerHTML, not innerHTML: the response is the exact same partial the
            // full page includes (see TeamController::index()), root element and
            // all, so it still carries its own `data-ajax-panel` marker after the
            // swap — nothing here needs to re-bind anything to keep working.
            panel.outerHTML = html;
            history.pushState({}, '', href);
        } catch (error) {
            if (error.name === 'AbortError') return;
            window.location.href = href;
        }
    }

    // Capture phase, on `document` — same reason as the dashboard panel's own click
    // listener: this has to run, and call preventDefault(), before admin.blade.php's
    // own click.capture on <body> checks defaultPrevented and (wrongly) flags a real
    // navigation.
    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[href]');
        if (!link) return;

        const panel = panelOf(link);
        if (!panel) return;

        if (link.hasAttribute('download') || link.target === '_blank') return;
        if (event.defaultPrevented || event.metaKey || event.ctrlKey
            || event.shiftKey || event.altKey || event.button !== 0) return;

        event.preventDefault();
        loadInto(panel, link.href);
    }, true);

    document.addEventListener('submit', (event) => {
        const form = event.target;
        const panel = panelOf(form);
        if (!panel) return;
        // Only GET — see the docblock above for why mutating forms are out of scope.
        if ((form.getAttribute('method') || 'GET').toUpperCase() !== 'GET') return;

        event.preventDefault();
        const url = new URL(form.getAttribute('action') || window.location.href, window.location.origin);
        url.search = new URLSearchParams(new FormData(form)).toString();
        loadInto(panel, url.href);
    }, true);

    window.addEventListener('popstate', () => window.location.reload());
})();

/* ------------------------------------------------------------------ settings page AJAX forms */

/**
 * Every mutating form on the Settings page (Form fields, Locations, Project types,
 * Unit types, Amenities, Measurement units, Branding, Email, KYC, Maps, Access) submits
 * via fetch instead of a real navigation, then refetches the #settings-tabs fragment
 * (tab, and on Locations the country/state selection, preserved) and swaps it in
 * whole. `tab` is client-side-only Alpine state — a real redirect-and-reload always
 * reset it back to the default, which is what made every single save on this page look
 * like it "reloaded and lost your place." One mechanism handles every toggle, save,
 * add and delete here rather than ~25 forms each needing their own bespoke AJAX +
 * DOM-patch logic — see SettingsController::settingsResponse() and the matching note
 * on every other settings-adjacent controller for the server side of this contract:
 * `{message: string, tone?: string, params?: object}`, `params` only from Locations,
 * telling the client which country/state to refetch with (an add has to select the
 * thing just added, not whatever was already in the address bar).
 *
 * Forms gated behind confirm-dialog.blade.php carry `data-confirm` and are skipped by
 * the plain `submit` listener below — their own x-on:submit.prevent calling
 * preventDefault() stops the browser's real navigation but NOT this capture-phase
 * listener from still seeing the same event, so without that check the request went
 * out immediately, before the dialog even opened. Once accepted, confirm-dialog calls
 * `form.submit()`, which bypasses submit listeners entirely (so the handler that
 * opened the dialog cannot re-fire and loop) after first dispatching a cancelable
 * `confirmed-submit` event — see confirm-dialog.blade.php's own note — which is what
 * this listens for instead, so a confirmed action goes through the exact same fetch
 * path as everything else on this page.
 */
(() => {
    const root = () => document.getElementById('settings-tabs');

    function refreshUrl(extraParams) {
        const params = new URLSearchParams(window.location.search);
        const scope = root();
        const tab = scope ? window.Alpine?.$data?.(scope)?.tab : null;
        if (tab) params.set('tab', tab);

        Object.entries(extraParams ?? {}).forEach(([key, value]) => {
            if (value === null || value === undefined || value === '') {
                params.delete(key);
            } else {
                params.set(key, String(value));
            }
        });

        return `${window.location.pathname}?${params.toString()}`;
    }

    async function refreshTabs(extraParams) {
        const scope = root();
        if (!scope) return;

        const response = await fetch(refreshUrl(extraParams), {
            headers: {'X-Requested-With': 'XMLHttpRequest'},
        });
        if (!response.ok) return;

        scope.outerHTML = await response.text();
    }

    /**
     * Dims and disables pointer events on the *whole* tabs region, not just the
     * submitting form — most row forms here (project types, unit types, amenities,
     * measurement units) are practically invisible empty <form> elements whose real
     * inputs/button live elsewhere in the row via the `form="…"` attribute, so dimming
     * only the form itself dimmed nothing a person could actually see. More importantly,
     * this is what stops a second row from being edited while the first row's
     * save-then-refresh is still in flight — see submitViaFetch's own note on why that
     * race is the thing this exists to prevent.
     */
    function setBusy(busy) {
        const scope = root();
        if (!scope) return;
        scope.classList.toggle('opacity-60', busy);
        scope.classList.toggle('pointer-events-none', busy);
    }

    async function submitViaFetch(form) {
        setBusy(true);

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json'},
                body: new FormData(form),
            });
            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                window.toast?.(data.message ?? 'Could not save. Please check the form.', 'error');
                return;
            }

            // Refresh BEFORE the toast, not after: the toast is the signal that it is
            // safe to touch the page again, so it firing while the swap is still in
            // flight left a window where editing a second row landed on content that
            // was about to be wiped out and replaced with the pre-edit server value —
            // the edit look like it silently "did not save".
            await refreshTabs(data.params);
            window.toast?.(data.message ?? 'Saved.', data.tone ?? 'success');
        } catch {
            window.toast?.('Could not reach the server. Please try again.', 'error');
        } finally {
            // setBusy(false) on the *current* #settings-tabs — root() re-queries fresh,
            // so this correctly targets the swapped-in node when refreshTabs() ran, and
            // the original one otherwise (an error before any swap happened).
            setBusy(false);
        }
    }

    function withinScope(form) {
        const scope = root();
        return !!scope && scope.contains(form) && !form.hasAttribute('data-no-ajax');
    }

    // `data-confirm` forms fire this same 'submit' event too — preventDefault() from
    // their own x-on:submit.prevent handler does not stop it reaching this capture
    // listener first, it only stops the browser's default navigation. Without this
    // check the real request went out immediately, before confirm-dialog even opened,
    // then went out a second time when the user actually clicked confirm.
    document.addEventListener('submit', (event) => {
        if (!withinScope(event.target) || event.target.hasAttribute('data-confirm')) return;
        event.preventDefault();
        submitViaFetch(event.target);
    }, true);

    document.addEventListener('confirmed-submit', (event) => {
        if (!withinScope(event.target)) return;
        event.preventDefault();
        submitViaFetch(event.target);
    }, true);
})();

/* ------------------------------------------------------------------ approvals detail AJAX */

/**
 * Approve, Reject and Edit on an Approvals detail page (`/admin/approvals/{id}`) submit
 * via fetch and refetch `#approval-detail` in place, instead of a full page reload — same
 * fetch-then-refresh contract as the settings-tabs block above, just scoped to this one
 * element. See ApprovalController::approve()/reject()/show() and
 * ChannelPartnerController::update() for the matching `$request->ajax()` fork on the
 * server side; `admin.approvals.partials.detail` is the exact partial both the full page
 * and this fetch render, so the two can never drift.
 *
 * Reset password is deliberately excluded — its dialog lives outside this element
 * entirely (mounted once at the layout level, shared with Team/Developers) and its
 * success flow reveals a generated password via a session-flashed value a fetch response
 * has no page load to hydrate from.
 *
 * The Edit form carries `data-manual-submit` because it already awaits file compression
 * before submitting — see the note on that form in the partial for why a capture-phase
 * listener has to explicitly skip it rather than trying to double-handle its submit.
 */
(() => {
    const root = () => document.getElementById('approval-detail');

    function withinScope(form) {
        const scope = root();
        return !!scope && scope.contains(form) && !form.hasAttribute('data-no-ajax');
    }

    function setBusy(busy) {
        const scope = root();
        if (!scope) return;
        scope.classList.toggle('opacity-60', busy);
        scope.classList.toggle('pointer-events-none', busy);
    }

    async function refresh() {
        const scope = root();
        if (!scope) return;

        const response = await fetch(window.location.href, {
            headers: {'X-Requested-With': 'XMLHttpRequest'},
        });
        if (!response.ok) return;

        scope.outerHTML = await response.text();
    }

    // Laravel's default 422 body is {message, errors: {field: [msgs]}} — the first
    // field message is a far more useful toast than the generic top-level message
    // ("The given data was invalid.") when one is available.
    function errorMessage(data) {
        if (data?.errors) {
            const first = Object.values(data.errors).flat()[0];
            if (first) return first;
        }

        return data?.message ?? 'Could not save. Please check the form.';
    }

    async function submitViaFetch(form) {
        setBusy(true);

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json'},
                body: new FormData(form),
            });
            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                window.toast?.(errorMessage(data), 'error');
                return;
            }

            // Refresh BEFORE the toast — same reasoning as settings' submitViaFetch:
            // the toast is the "safe to touch the page again" signal, and firing it
            // early would let a second action land mid-swap.
            await refresh();
            window.toast?.(data.message ?? 'Saved.', data.tone ?? 'success');
        } catch {
            window.toast?.('Could not reach the server. Please try again.', 'error');
        } finally {
            // setBusy(false) on the *current* #approval-detail — root() re-queries
            // fresh, so this targets the swapped-in node when refresh() ran, and the
            // original one otherwise (an error before any swap happened).
            setBusy(false);
        }
    }

    // Exposed so the Edit form's own x-on:submit handler can call it directly, after
    // its own compression step, instead of the generic listener below ever seeing that
    // form's submit event untouched.
    window.submitApprovalForm = submitViaFetch;

    document.addEventListener('submit', (event) => {
        if (!withinScope(event.target) || event.target.hasAttribute('data-manual-submit')) return;
        event.preventDefault();
        submitViaFetch(event.target);
    }, true);
})();
