@props(['name' => '', 'size' => 'md', 'tone' => null, 'src' => null])

{{--
    `src` is the stored path — a broker's registration photo or a developer's logo, both
    relative to the public disk. Pass the path, not a URL: the call sites should not each
    remember which disk it is on. Initials remain the fallback, so a record without an
    upload looks the same as it always did.
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

// Deterministic tone from the name so the same person keeps the same swatch.
$palette = [
    'bg-primary-soft text-primary-dark ring-primary-ring',
    'bg-info-soft text-info ring-info-ring',
    'bg-success-soft text-success ring-success-ring',
    'bg-warning-soft text-warning ring-warning-ring',
];
$chosen = $tone ?? $palette[crc32($name) % count($palette)];
@endphp

@if($src)
    <img src="{{ \Illuminate\Support\Str::startsWith($src, ['http://', 'https://']) ? $src : asset('storage/' . $src) }}"
         alt="{{ $name }}"
         title="{{ $name }}"
         {{ $attributes->merge(['class' => 'object-cover ring-1 ring-inset ring-line shrink-0 rounded-avatar ' . ($sizes[$size] ?? $sizes['md'])]) }} />
@else
    <span {{ $attributes->merge(['class' => 'inline-flex items-center justify-center font-semibold ring-1 ring-inset shrink-0 select-none rounded-avatar ' . ($sizes[$size] ?? $sizes['md']) . ' ' . $chosen]) }}
          title="{{ $name }}">
        {{ $initials ?: '—' }}
    </span>
@endif
