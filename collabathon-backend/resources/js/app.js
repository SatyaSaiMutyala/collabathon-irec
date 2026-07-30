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

// Alpine loads from a CDN and evaluates its expressions from the global scope, so the
// wizard and the credential dialogs reach these through `window` rather than an import.
window.compressFileInputs = compressFileInputs;
window.makePassword = makePassword;
