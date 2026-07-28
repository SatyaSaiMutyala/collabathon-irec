@props(['align' => 'left', 'sortable' => false, 'sort' => null, 'hide' => null])

@php
$alignClass = match ($align) {
    'right' => 'text-right',
    'center' => 'text-center',
    default => 'text-left',
};

/**
 * `hide` drops a lower-priority column below the given breakpoint so the columns that
 * matter (identity, status, actions) survive on a phone. Matching classes go on the <td>.
 */
$hideClass = match ($hide) {
    'sm' => 'hidden sm:table-cell',
    'md' => 'hidden md:table-cell',
    'lg' => 'hidden lg:table-cell',
    'xl' => 'hidden xl:table-cell',
    default => '',
};

// Sorting is a link, not JS — it re-queries the server so the order applies to the
// whole result set rather than the current page.
$sortKey = $sort ?? null;
$active = $sortKey && request('sort') === $sortKey;
$nextDirection = $active && request('direction', 'desc') === 'desc' ? 'asc' : 'desc';
$sortUrl = $sortKey
    ? url()->current() . '?' . http_build_query(array_merge(
        request()->except(['sort', 'direction', 'page']),
        ['sort' => $sortKey, 'direction' => $nextDirection]
    ))
    : null;
@endphp

<th scope="col"
    @if($active) aria-sort="{{ request('direction', 'desc') === 'asc' ? 'ascending' : 'descending' }}" @endif
    {{ $attributes->merge(['class' => "px-4 py-2.5 text-[11px] font-semibold uppercase tracking-[0.06em] whitespace-nowrap $alignClass $hideClass " . ($active ? 'text-ink-2' : 'text-ink-3')]) }}>
    @if($sortUrl)
        <a href="{{ $sortUrl }}" class="inline-flex items-center gap-1 hover:text-ink-2 transition-colors">
            {{ $slot }}
            @if($active)
                <x-icon :name="request('direction', 'desc') === 'asc' ? 'arrow-up' : 'arrow-down'" class="w-3 h-3" />
            @else
                <x-icon name="chevron-up-down" class="w-3.5 h-3.5 opacity-60" />
            @endif
        </a>
    @elseif($sortable)
        <span class="inline-flex items-center gap-1">{{ $slot }}</span>
    @else
        {{ $slot }}
    @endif
</th>
