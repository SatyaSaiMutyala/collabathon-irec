@props([
    'searchPlaceholder' => 'Search…',
    'searchable' => true,
    'paginator' => null,
    'label' => 'records',
    'emptyTitle' => 'No records found',
    'emptyDescription' => 'Try a different search term or clear the filters.',
    'emptyIcon' => 'inbox',
])

{{--
    Server-side table. The search box submits a GET to the current route, so filtering
    runs in SQL over the whole table — not over the rows that happen to be on this page.
    Any other active query params ride along as hidden inputs so searching does not
    silently drop a filter, and `page` is deliberately dropped so a new search starts
    at page 1 rather than a page that may no longer exist.
--}}
<div {{ $attributes->merge(['class' => 'bg-panel border border-line rounded-2xl shadow-card flex flex-col min-w-0']) }}>

    @if($searchable || isset($filters) || isset($actions))
        <div class="px-4 py-3 border-b border-line-soft flex flex-wrap items-center gap-2.5 shrink-0">
            @if($searchable)
                <form method="GET" action="{{ url()->current() }}" class="flex-1 min-w-[200px] max-w-sm">
                    @foreach(request()->except(['search', 'page']) as $key => $value)
                        @if(is_scalar($value))
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endif
                    @endforeach

                    <label class="relative block">
                        <span class="sr-only">{{ $searchPlaceholder }}</span>
                        <x-icon name="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-ink-3 pointer-events-none" />
                        <input type="search" name="search" value="{{ request('search') }}"
                               placeholder="{{ $searchPlaceholder }}"
                               class="w-full h-9 pl-9 pr-3 rounded-lg bg-canvas border border-transparent shadow-card text-[13px] text-ink
                                      placeholder:text-ink-3 hover:border-line focus:bg-panel focus:border-primary-ring
                                      focus:outline-none transition-colors">
                    </label>
                </form>
            @endif

            @isset($filters)
                <div class="flex flex-wrap items-center gap-2">{{ $filters }}</div>
            @endisset

            <div class="flex items-center gap-2 ml-auto">
                @if($paginator)
                    <p class="text-[12px] text-ink-3 nums">
                        {{ number_format($paginator->total()) }} {{ Str::plural(Str::singular($label), $paginator->total()) }}
                    </p>
                @endif
                @isset($actions)
                    {{ $actions }}
                @endisset
            </div>
        </div>
    @endif

    @if($paginator && $paginator->total() === 0)
        <x-empty-state :icon="$emptyIcon" :title="$emptyTitle" :description="$emptyDescription">
            @if(request()->hasAny(['search', 'status', 'city', 'type', 'developer_id']))
                <x-slot:action>
                    <x-button variant="outline" size="sm" tag="a" href="{{ url()->current() }}">Clear filters</x-button>
                </x-slot:action>
            @endif
        </x-empty-state>
    @else
        <div class="overflow-x-auto scrollbar-slim">
            <table class="w-full text-left border-collapse">
                @isset($head)
                    <thead>
                        <tr class="border-b border-line-soft">
                            {{ $head }}
                        </tr>
                    </thead>
                @endisset
                <tbody class="divide-y divide-line-soft">
                    {{ $slot }}
                </tbody>
            </table>
        </div>

        @if($paginator)
            <div class="px-4 py-3 border-t border-line-soft shrink-0">
                <x-pagination :paginator="$paginator" :label="$label" />
            </div>
        @endif
    @endif

    @isset($footer)
        <div class="px-4 py-3 border-t border-line-soft shrink-0">{{ $footer }}</div>
    @endisset
</div>
