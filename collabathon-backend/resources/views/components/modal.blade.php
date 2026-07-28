@props(['title'])

<div x-data="{ open: false }" class="inline-block">
    <div @click="open = true">
        {{ $trigger }}
    </div>

    <div x-show="open" x-cloak style="display:none" class="fixed inset-0 z-50 flex items-center justify-center px-4">
        <div x-show="open" x-transition.opacity @click="open = false" class="absolute inset-0 bg-navy/50"></div>

        <div x-show="open" x-transition
            class="relative bg-white rounded-2xl w-full max-w-md shadow-2xl">
            <div class="px-6 py-4 border-b border-border flex items-center justify-between">
                <h3 class="text-[15px] font-semibold text-navy">{{ $title }}</h3>
                <button @click="open = false" class="text-text-muted hover:text-navy">
                    <x-icon name="x" class="w-4.5 h-4.5" />
                </button>
            </div>
            <div class="px-6 py-5">
                {{ $slot }}
            </div>
        </div>
    </div>
</div>
