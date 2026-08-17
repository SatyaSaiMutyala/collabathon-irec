@props(['name' => '', 'size' => 'md', 'tone' => null, 'src' => null, 'shape' => 'circle'])

{{--
    `src` is the stored path — a broker's registration photo or a developer's logo, both
    relative to the public disk. Pass the path, not a URL: the call sites should not each
    remember which disk it is on. Initials remain the fallback, so a record without an
    upload looks the same as it always did.

    `shape="square"` is for company logos, not people: a person's photo is roughly square
    to begin with and a circular crop of it looks intentional, but a logo is usually a wide
    wordmark, and cropping it into a circle cuts off the edges of the actual mark. Square
    mode is a plain, unrounded 5:2 frame (no ring, no padding, no border-radius — a logo is
    the whole visual, not a photo inside a decorated frame) with `object-contain` instead of
    `object-cover`, so a logo uploaded through <x-logo-field> (which crops to that same 5:2
    ratio before it's ever saved) fills the frame edge to edge; it's the safety net for
    anything that still doesn't match. Leave every person-photo call site on the default
    `circle`.
--}}

@php
$parts = array_values(array_filter(preg_split('/\s+/', trim($name)) ?: []));

// Registration stores the name as "<suffix> <full name as per RERA>", so a broker who
// picked "Mr." is saved as "Mr. Satya" and would otherwise be drawn as "MS" — the
// honorific rather than the person. Only a leading one is dropped, and never the last
// remaining word, so a name recorded solely as "Dr" still renders something.
$honorifics = ['mr', 'mrs', 'ms', 'miss', 'dr', 'eng', 'er', 'prof', 'shri', 'smt', 'sri'];
if (count($parts) > 1 && in_array(rtrim(mb_strtolower($parts[0]), '.'), $honorifics, true)) {
    array_shift($parts);
}

$initials = strtoupper(
    mb_substr($parts[0] ?? '', 0, 1) . (count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '')
);

$sizes = [
    'xs' => 'w-6 h-6 text-[10px]',
    'sm' => 'w-7 h-7 text-[11px]',
    'md' => 'w-9 h-9 text-[12.5px]',
    'lg' => 'w-11 h-11 text-[15px]',
];

// A logo is a wide wordmark, not a square headshot — this size map is a fixed 5:2 ratio
// per tier (not a reuse of $sizes above) so a properly-cropped logo fills its frame
// instead of sitting tiny and letterboxed inside a square one. Arbitrary-value classes
// rather than the nearest stock Tailwind pair, so the ratio lands exactly.
$logoSizes = [
    'xs' => 'w-[40px] h-[16px] text-[10px]',
    'sm' => 'w-[48px] h-[19px] text-[11px]',
    'md' => 'w-[68px] h-[27px] text-[12.5px]',
    'lg' => 'w-[110px] h-[44px] text-[15px]',
];

// Deterministic tone from the name so the same person keeps the same swatch.
$palette = [
    'bg-primary-soft text-primary-dark ring-primary-ring',
    'bg-info-soft text-info ring-info-ring',
    'bg-success-soft text-success ring-success-ring',
    'bg-warning-soft text-warning ring-warning-ring',
];
$chosen = $tone ?? $palette[crc32($name) % count($palette)];

$radius = $shape === 'square' ? 'rounded-none' : 'rounded-avatar';
$fit = $shape === 'square' ? 'object-contain bg-panel' : 'object-cover';
$dims = $shape === 'square' ? ($logoSizes[$size] ?? $logoSizes['md']) : ($sizes[$size] ?? $sizes['md']);
// A ring reads as an intentional frame on a person photo, but on a logo it shows up as
// a stray line cutting across the image itself — the logo is the whole visual, not a
// photo inside a frame, so square mode skips it entirely (both for a real image and for
// the initials placeholder — the latter keeps its own tone-coloured ring, from $chosen,
// only in circle mode).
$imgRing = $shape === 'square' ? '' : 'ring-1 ring-inset ring-line';
$fallbackRing = $shape === 'square' ? '' : 'ring-1 ring-inset';
@endphp

@if($src)
    <img src="{{ \Illuminate\Support\Str::startsWith($src, ['http://', 'https://']) ? $src : asset('storage/' . $src) }}"
         alt="{{ $name }}"
         title="{{ $name }}"
         {{ $attributes->merge(['class' => implode(' ', array_filter([$fit, $imgRing, 'shrink-0', $radius, $dims]))]) }} />
@else
    <span {{ $attributes->merge(['class' => implode(' ', array_filter([
             'inline-flex items-center justify-center font-semibold shrink-0 select-none',
             $fallbackRing, $radius, $dims, $chosen,
         ]))]) }}
          title="{{ $name }}">
        {{ $initials ?: '—' }}
    </span>
@endif
