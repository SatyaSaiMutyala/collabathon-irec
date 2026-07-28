@props(['tone' => 'neutral', 'dot' => false, 'size' => 'md'])

@php
// Status colour never carries meaning alone — every badge renders its label as
// text, and `dot` adds a second (shape) channel where badges sit in a column.
$tones = [
    'success' => ['chip' => 'bg-success-soft text-success ring-success-ring', 'dot' => 'bg-success'],
    'danger'  => ['chip' => 'bg-danger-soft text-danger ring-danger-ring',    'dot' => 'bg-danger'],
    'warning' => ['chip' => 'bg-warning-soft text-warning ring-warning-ring', 'dot' => 'bg-warning'],
    'info'    => ['chip' => 'bg-info-soft text-info ring-info-ring',          'dot' => 'bg-info'],
    'primary' => ['chip' => 'bg-primary-soft text-primary-dark ring-primary-ring', 'dot' => 'bg-primary'],
    'neutral' => ['chip' => 'bg-canvas text-ink-2 ring-line',                 'dot' => 'bg-ink-3'],
];

$sizes = [
    'sm' => 'px-2 py-0.5 text-[10.5px] gap-1.5',
    'md' => 'px-2.5 py-1 text-[11.5px] gap-1.5',
];

$t = $tones[$tone] ?? $tones['neutral'];
$s = $sizes[$size] ?? $sizes['md'];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full font-medium ring-1 ring-inset whitespace-nowrap {$t['chip']} $s"]) }}>
    @if($dot)
        <span class="w-1.5 h-1.5 rounded-full shrink-0 {{ $t['dot'] }}"></span>
    @endif
    {{ $slot }}
</span>
