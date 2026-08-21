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
            <div class="px-4 py-3 border-t border-line-soft shrink-0 flex flex-wrap items-center justify-between gap-3">
                <x-pagination :paginator="$paginator" :label="$label" />

                {{--
                    Per-page submits on change, same auto-submit shape as filter-select.
                    `page` is dropped so changing this returns to page 1 — otherwise a
                    smaller page size can land past the new last page and show nothing.
                    The option list always includes whatever the paginator is *actually*
                    showing right now (via array_unique), so a controller whose own
                    default isn't one of the five standard sizes still renders a
                    correctly-selected value instead of silently matching none of them.
                --}}
                <form method="GET" action="{{ url()->current() }}" class="flex items-center gap-2 shrink-0">
                    @foreach(request()->except(['per_page', 'page']) as $key => $value)
                        @if(is_scalar($value))
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endif
                    @endforeach

                    <label class="flex items-center gap-1.5">
                        <span class="text-[12px] text-ink-3 whitespace-nowrap">Rows per page</span>
                        <span class="relative">
                            <select name="per_page" onchange="this.form.submit()"
                                    class="h-8 pl-2.5 pr-7 rounded-lg bg-canvas text-ink-2 text-[12.5px] appearance-none
                                           cursor-pointer transition-colors hover:bg-line-soft focus:outline-none">
                                @foreach(collect([5, 10, 20, 50, 100])->push($paginator->perPage())->unique()->sort()->values() as $size)
                                    <option value="{{ $size }}" @selected($paginator->perPage() === $size)>{{ $size }}</option>
                                @endforeach
                            </select>
                            <x-icon name="chevron-down" class="w-3.5 h-3.5 absolute right-2 top-1/2 -translate-y-1/2 text-ink-3 pointer-events-none" />
                        </span>
                    </label>
                </form>
            </div>
        @endif
    @endif

    @isset($footer)
        <div class="px-4 py-3 border-t border-line-soft shrink-0">{{ $footer }}</div>
    @endisset
</div>
