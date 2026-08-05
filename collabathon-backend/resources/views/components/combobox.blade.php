@props(['label' => null, 'options' => [], 'required' => false, 'hint' => null, 'name' => null, 'selected' => null, 'placeholder' => 'Search…'])

@php
/**
 * A searchable `<select>` replacement for option lists too long to scan by eye —
 * this project's `<select>` (see select-field.blade.php) is fine for "Draft/Active",
 * not for "which of 40 developers". Same value semantics as select-field: submits a
 * scalar under $name, keyed options submit the key, a flat list submits the label.
 *
 * No library — Alpine only, matching the rest of this panel. The option list is
 * small enough (dozens, not thousands) to filter client-side; a couple hundred
 * options would still be fine, this is not virtualised.
 */
$id = $name ?? 'f-' . \Illuminate\Support\Str::slug($label ?? uniqid());
$currentValue = old($id, $selected ?? data_get($formRecord ?? null, $id));
$hasError = $errors->has($id);

$optionList = $options instanceof \Illuminate\Support\Collection ? $options->all() : (array) $options;
$isFlatList = array_is_list($optionList);

$jsOptions = [];
$currentLabel = null;
foreach ($optionList as $key => $option) {
    $value = (string) ($isFlatList ? $option : $key);
    $jsOptions[] = ['value' => $value, 'label' => (string) $option];
    if ($currentValue !== null && (string) $currentValue === $value) {
        $currentLabel = (string) $option;
    }
}

$toneClass = $hasError
    ? 'border-danger focus-within:border-danger focus-within:ring-danger-ring'
    : 'border-line focus-within:border-primary focus-within:ring-primary-ring';
@endphp

<div {{ $attributes->only('class') }}
     x-data="{
         open: false,
         query: '',
         value: @js($currentValue !== null ? (string) $currentValue : ''),
         label: @js($currentLabel),
         options: @js($jsOptions),
         get filtered() {
             const q = this.query.trim().toLowerCase();
             return q === '' ? this.options : this.options.filter(o => o.label.toLowerCase().includes(q));
         },
         select(opt) {
             this.value = opt.value;
             this.label = opt.label;
             this.open = false;
             this.query = '';
         },
     }"
     @click.outside="open = false"
     @keydown.escape="open = false"
     class="relative">

    @if($label)
        <label class="flex items-center gap-1 text-[12.5px] font-medium text-ink mb-1.5">
            {{ $label }}
            @if($required)<span class="text-danger" aria-hidden="true">*</span>@endif
        </label>
    @endif

    <input type="hidden" name="{{ $id }}" x-model="value">

    <button type="button" @click="open = ! open; $nextTick(() => open && $refs.search.focus())"
            :aria-expanded="open.toString()" aria-haspopup="listbox"
            @if($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
            class="w-full h-10 pl-3.5 pr-9 rounded-lg bg-panel border text-[13.5px] text-left
                   focus:outline-none focus:ring-[3px] transition-[border-color,box-shadow] relative {{ $toneClass }}"
            :class="!label && 'text-ink-3'">
        <span x-text="label || @js($placeholder)" class="block truncate"></span>
        <x-icon name="chevron-down" class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-ink-3 pointer-events-none" />
    </button>

    <div x-show="open" x-cloak
         x-transition:enter="transition ease-out duration-120"
         x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         class="absolute z-20 mt-1.5 w-full bg-panel border border-line rounded-xl shadow-pop overflow-hidden">
        <div class="p-2 border-b border-line-soft">
            <div class="relative">
                <x-icon name="search" class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-ink-3 pointer-events-none" />
                <input type="text" x-ref="search" x-model="query" placeholder="{{ $placeholder }}"
                       class="w-full h-8 pl-8 pr-2.5 rounded-lg bg-canvas border border-transparent text-[12.5px] text-ink
                              placeholder:text-ink-3 focus:outline-none focus:border-primary-ring">
            </div>
        </div>

        <div class="max-h-56 overflow-y-auto scrollbar-slim py-1" role="listbox">
            <template x-for="opt in filtered" :key="opt.value">
                <button type="button" @click="select(opt)"
                        class="w-full flex items-center justify-between gap-2 px-3 py-2 text-left text-[12.5px] text-ink
                               hover:bg-canvas transition-colors"
                        :class="opt.value === value && 'bg-primary-soft text-primary-dark font-medium'"
                        role="option" :aria-selected="opt.value === value">
                    <span x-text="opt.label" class="truncate"></span>
                    <x-icon name="check" class="w-3.5 h-3.5 shrink-0" x-show="opt.value === value" x-cloak />
                </button>
            </template>

            <p x-show="filtered.length === 0" x-cloak class="px-3 py-4 text-[12px] text-ink-3 text-center">
                No matches.
            </p>
        </div>
    </div>

    @error($id)
        <p id="{{ $id }}-error" class="flex items-start gap-1.5 text-[11.5px] text-danger mt-1.5">
            <x-icon name="x" class="w-3.5 h-3.5 shrink-0 mt-px" />
            <span>{{ $message }}</span>
        </p>
    @elseif($hint)
        <p class="text-[11.5px] text-ink-3 mt-1.5">{{ $hint }}</p>
    @enderror
</div>
