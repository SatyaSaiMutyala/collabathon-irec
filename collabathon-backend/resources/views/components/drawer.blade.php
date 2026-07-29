@props(['title' => '', 'subtitle' => null, 'width' => 'max-w-lg'])

{{--
    Right-hand review panel. Used where a row needs a full record shown without
    losing the list behind it — an approval decision, an account detail.
--}}
<div x-data="{ open: false }" @keydown.escape.window="open = false" class="contents">
    <div @click="open = true" class="contents">
        {{ $trigger }}
    </div>

    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-50" role="dialog" aria-modal="true">
            <div x-show="open"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                 @click="open = false"
                 class="absolute inset-0 bg-scrim"></div>

            <div x-show="open"
                 x-transition:enter="transition ease-out duration-220"
                 x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                 x-transition:leave="transition ease-in duration-180"
                 x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
                 class="absolute inset-y-0 right-0 w-full {{ $width }} bg-panel shadow-modal flex flex-col">

                <header class="px-6 py-4 border-b border-line flex items-start justify-between gap-4 shrink-0">
                    <div class="min-w-0">
                        <h2 class="text-[15px] font-semibold text-ink tracking-[-0.01em]">{{ $title }}</h2>
                        @if($subtitle)
                            <p class="text-[12.5px] text-ink-3 mt-0.5">{{ $subtitle }}</p>
                        @endif
                    </div>
                    <button type="button" @click="open = false" aria-label="Close panel"
                            class="text-ink-3 hover:text-ink hover:bg-canvas rounded-lg p-1.5 -m-1 transition-colors shrink-0">
                        <x-icon name="x" class="w-4.5 h-4.5" />
                    </button>
                </header>

                <div class="flex-1 overflow-y-auto scrollbar-slim px-6 py-5">
                    {{ $slot }}
                </div>

                @isset($footer)
                    <footer class="px-6 py-4 border-t border-line bg-canvas shrink-0">
                        {{ $footer }}
                    </footer>
                @endisset
            </div>
        </div>
    </template>
</div>
