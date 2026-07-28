@props(['paginator', 'label' => 'records'])

@php
    /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator $paginator */
    $from = $paginator->firstItem();
    $to = $paginator->lastItem();
    $total = $paginator->total();
@endphp

@if($paginator->total() > 0)
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-[12px] text-ink-3 nums">
            Showing <span class="text-ink-2 font-medium">{{ number_format($from) }}–{{ number_format($to) }}</span>
            of <span class="text-ink-2 font-medium">{{ number_format($total) }}</span> {{ $label }}
        </p>

        @if($paginator->hasPages())
            <nav class="flex items-center gap-1" aria-label="Pagination">
                {{-- Previous --}}
                @if($paginator->onFirstPage())
                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-ink-3/50 cursor-not-allowed">
                        <x-icon name="chevron-left" class="w-4 h-4" />
                    </span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous page"
                       class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-ink-2 hover:bg-canvas transition-colors">
                        <x-icon name="chevron-left" class="w-4 h-4" />
                    </a>
                @endif

                {{-- Windowed page numbers: the paginator elements array already
                     collapses long ranges into '...' separators. --}}
                @foreach($paginator->onEachSide(1)->links()->elements as $element)
                    @if(is_string($element))
                        <span class="px-1.5 text-[12px] text-ink-3">{{ $element }}</span>
                    @endif

                    @if(is_array($element))
                        @foreach($element as $page => $url)
                            @if($page == $paginator->currentPage())
                                <span aria-current="page"
                                      class="inline-flex items-center justify-center min-w-8 h-8 px-2 rounded-lg
                                             bg-navy text-white text-[12.5px] font-medium nums">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}"
                                   class="inline-flex items-center justify-center min-w-8 h-8 px-2 rounded-lg
                                          text-ink-2 text-[12.5px] hover:bg-canvas transition-colors nums">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                {{-- Next --}}
                @if($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next page"
                       class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-ink-2 hover:bg-canvas transition-colors">
                        <x-icon name="chevron-right" class="w-4 h-4" />
                    </a>
                @else
                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-ink-3/50 cursor-not-allowed">
                        <x-icon name="chevron-right" class="w-4 h-4" />
                    </span>
                @endif
            </nav>
        @endif
    </div>
@endif
