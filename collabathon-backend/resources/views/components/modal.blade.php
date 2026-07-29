@props(['title', 'subtitle' => null, 'width' => 'max-w-lg', 'open' => false])

<div x-data="{ open: {{ $open ? 'true' : 'false' }} }" @keydown.escape.window="open = false" class="inline-block">
    <div @click="open = true">
        {{ $trigger }}
    </div>

    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-start justify-center px-4 py-10 overflow-y-auto"
             role="dialog" aria-modal="true">
            <div x-show="open"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                 @click="open = false"
                 class="fixed inset-0 bg-navy/40 backdrop-blur-[2px]"></div>

            <div x-show="open"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-y-3 scale-[0.98]"
                 x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-[0.98]"
                 class="relative bg-panel rounded-2xl w-full {{ $width }} shadow-modal my-auto">

                <header class="px-6 py-4 border-b border-line flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <h3 class="text-[15px] font-semibold text-ink tracking-[-0.01em]">{{ $title }}</h3>
                        @if($subtitle)
                            <p class="text-[12.5px] text-ink-3 mt-0.5">{{ $subtitle }}</p>
                        @endif
                    </div>
                    <button type="button" @click="open = false" aria-label="Close dialog"
                            class="text-ink-3 hover:text-ink hover:bg-canvas rounded-lg p-1.5 -m-1 transition-colors shrink-0">
                        <x-icon name="x" class="w-4.5 h-4.5" />
                    </button>
                </header>

                <div class="px-6 py-5">
                    {{ $slot }}
                </div>

                @isset($footer)
                    <footer class="px-6 py-4 border-t border-line bg-canvas/60 rounded-b-2xl">{{ $footer }}</footer>
                @endisset
            </div>
        </div>
    </template>
</div>
