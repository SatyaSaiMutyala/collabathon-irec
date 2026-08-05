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

const LEAFLET_CSS = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
const LEAFLET_JS = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';

// Hyderabad. Only ever seen when a developer has no coordinates yet and the admin opens
// the map before searching — an arbitrary centre is better than a view of the null island.
const FALLBACK_CENTRE = [17.4401, 78.3489];

let leafletPromise = null;

/**
 * Loads Leaflet the first time the map is opened, not on page load.
 *
 * It is ~150 KB of JS and CSS for one control on one form, and most admin pages never
 * show it. Deferring keeps that cost off every other screen, and the promise is cached so
 * opening the map a second time is instant.
 */
function loadLeaflet() {
    if (leafletPromise) return leafletPromise;

    leafletPromise = new Promise((resolve, reject) => {
        const css = document.createElement('link');
        css.rel = 'stylesheet';
        css.href = LEAFLET_CSS;
        document.head.appendChild(css);

        const script = document.createElement('script');
        script.src = LEAFLET_JS;
        script.onload = () => resolve(window.L);
        script.onerror = () => reject(new Error('Leaflet failed to load'));
        document.head.appendChild(script);
    });

    return leafletPromise;
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

            let L;
            try {
                L = await loadLeaflet();
            } catch {
                this.error = 'The map could not be loaded. Type the address instead.';
                return;
            }

            // Leaflet measures its container on init. Inside a modal that was display:none
            // a moment ago that measurement is 0x0 and the tiles render as a grey sliver,
            // so the map is built after the DOM has settled and told to re-measure.
            await this.$nextTick();

            const centre = this.latitude && this.longitude
                ? [Number(this.latitude), Number(this.longitude)]
                : FALLBACK_CENTRE;

            if (! this.map) {
                this.map = L.map(this.$refs.map).setView(centre, this.latitude ? 16 : 11);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap contributors',
                }).addTo(this.map);

                this.map.on('click', (event) => this.dropPin(event.latlng.lat, event.latlng.lng));
            } else {
                this.map.setView(centre, this.latitude ? 16 : 11);
            }

            setTimeout(() => this.map.invalidateSize(), 60);

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
            const L = window.L;
            if (! L || ! this.map) return;

            if (this.marker) {
                this.marker.setLatLng([lat, lng]);
            } else {
                this.marker = L.marker([lat, lng], { draggable: true }).addTo(this.map);
                this.marker.on('dragend', () => {
                    const p = this.marker.getLatLng();
                    this.dropPin(p.lat, p.lng);
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

// Alpine loads from a CDN and evaluates its expressions from the global scope, so the
// wizard and the credential dialogs reach these through `window` rather than an import.
window.compressFileInputs = compressFileInputs;
window.makePassword = makePassword;
window.addressFinder = addressFinder;
