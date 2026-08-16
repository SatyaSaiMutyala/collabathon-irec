@props(['export' => []])

{{--
    Custom-column export. The "Export" button opens a modal listing every exportable field,
    grouped into titled sections with a checkbox each. The admin ticks what they want (all
    ticked by default), optionally starts from a quick-select template, then downloads
    Excel (CSV) or opens the PDF — carrying ONLY the ticked columns.

    The ticked keys ride on the same list URL as `&columns=`, alongside whatever
    search/filter/sort the page is showing; ExportsList::exportList() intersects them with
    the columns the page actually offers. All ticked → the param is omitted (server default).

    Self-contained rather than built on <x-modal>: one Alpine scope owns the open state, the
    selection, the templates and the two export links together, with no teleport-scope seam.

    Excel uses `download` and PDF uses `target=_blank` — the two link kinds the admin layout
    skips when it would otherwise drop into its loading skeleton (see cp.blade.php).

    @param  array{groups: array<string,array<string,string>>, templates?: array<string,array<int,string>>, default?: array<int,string>}  $export
--}}

@php
    $groups = $export['groups'] ?? [];
    $templates = $export['templates'] ?? [];

    $allKeys = collect($groups)->flatMap(fn ($cols) => array_keys($cols))->values()->all();
    // Nothing supplied as default means "start with everything ticked".
    $defaultKeys = $export['default'] ?? $allKeys;

    // Bases without the columns param — Alpine appends the ticked keys. page=null drops the
    // paginated slice so the export is the whole filtered set.
    $excelBase = request()->fullUrlWithQuery(['export' => 'excel', 'page' => null, 'columns' => null]);
    $pdfBase = request()->fullUrlWithQuery(['export' => 'pdf', 'page' => null, 'columns' => null]);
@endphp

<div x-data="exportSelector({
        all: @js($allKeys),
        defaults: @js($defaultKeys),
        templates: @js($templates),
        excelBase: @js($excelBase),
        pdfBase: @js($pdfBase),
     })"
     @keydown.escape.window="open = false">

    <x-button variant="outline" icon="download" icon-right="chevron-down" @click="open = true">Export</x-button>

    <template x-teleport="body">
        <div x-show="open" x-cloak
             class="fixed inset-0 z-[60] flex items-start justify-center px-4 py-8 overflow-y-auto"
             role="dialog" aria-modal="true" aria-label="Choose export columns">

            <div x-show="open" x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                 @click="open = false" class="fixed inset-0 bg-scrim"></div>

            <div x-show="open"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-y-3 scale-[0.98]"
                 x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-[0.98]"
                 class="relative bg-panel rounded-2xl w-full max-w-4xl shadow-modal my-auto flex flex-col max-h-[86vh]">

                {{-- Header --}}
                <header class="px-6 py-4 border-b border-line flex items-start justify-between gap-4 shrink-0">
                    <div class="flex items-start gap-3 min-w-0">
                        <span class="w-9 h-9 rounded-xl bg-primary-soft text-primary-dark flex items-center justify-center shrink-0">
                            <x-icon name="download" class="w-[18px] h-[18px]" />
                        </span>
                        <div class="min-w-0">
                            <h3 class="text-[15px] font-semibold text-ink tracking-[-0.01em]">Export columns</h3>
                            <p class="text-[12.5px] text-ink-3 mt-0.5">
                                Tick the fields to include, then download.
                                <span class="text-ink-2 font-medium nums" x-text="`${count} of ${total} selected`"></span>
                            </p>
                        </div>
                    </div>
                    <button type="button" @click="open = false" aria-label="Close"
                            class="text-ink-3 hover:text-ink hover:bg-canvas rounded-lg p-1.5 -m-1 transition-colors shrink-0">
                        <x-icon name="x" class="w-4.5 h-4.5" />
                    </button>
                </header>

                {{-- Quick-select toolbar --}}
                <div class="px-6 py-3 border-b border-line-soft bg-canvas flex flex-wrap items-center gap-2 shrink-0">
                    <span class="text-[11px] font-semibold uppercase tracking-[0.07em] text-ink-3 mr-1">Quick select</span>
                    @foreach($templates as $name => $keys)
                        <button type="button" @click="applyTemplate(@js(array_values($keys)))"
                                class="h-7 px-3 rounded-lg border border-line bg-panel text-[12px] font-medium text-ink-2
                                       hover:text-ink hover:border-ink-3 transition-colors">{{ $name }}</button>
                    @endforeach
                    <span class="w-px h-4 bg-line mx-0.5"></span>
                    <button type="button" @click="selectAll()"
                            class="h-7 px-3 rounded-lg border border-line bg-panel text-[12px] font-medium text-primary-dark
                                   hover:bg-primary-soft transition-colors">Select all</button>
                    <button type="button" @click="selectNone()"
                            class="h-7 px-3 rounded-lg border border-line bg-panel text-[12px] font-medium text-ink-2
                                   hover:text-ink hover:border-ink-3 transition-colors">Deselect all</button>
                </div>

                {{-- Grouped checkboxes --}}
                <div class="px-6 py-4 overflow-y-auto scrollbar-slim grow">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        @foreach($groups as $groupLabel => $columns)
                            @php $groupKeys = array_keys($columns); @endphp
                            <section class="rounded-xl border border-line overflow-hidden flex flex-col">
                                <header class="px-3 py-2 bg-canvas border-b border-line-soft flex items-center justify-between gap-2">
                                    <span class="text-[12px] font-semibold text-ink truncate">{{ $groupLabel }}</span>
                                    <label class="flex items-center cursor-pointer shrink-0" title="Toggle group">
                                        <input type="checkbox"
                                               x-ref="grp{{ $loop->index }}"
                                               :checked="groupAll(@js($groupKeys))"
                                               x-effect="$refs.grp{{ $loop->index }}.indeterminate = groupSome(@js($groupKeys))"
                                               @change="toggleGroup(@js($groupKeys), $event.target.checked)"
                                               class="w-3.5 h-3.5 rounded border-line text-primary focus:ring-primary-ring">
                                    </label>
                                </header>
                                <div class="p-1.5 space-y-0.5">
                                    @foreach($columns as $key => $label)
                                        <label class="flex items-center gap-2.5 px-2 py-1.5 rounded-lg hover:bg-canvas cursor-pointer select-none">
                                            <input type="checkbox" value="{{ $key }}" x-model="selected"
                                                   class="w-3.5 h-3.5 rounded border-line text-primary focus:ring-primary-ring shrink-0">
                                            <span class="text-[12.5px] text-ink-2 min-w-0 truncate">{{ $label }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </section>
                        @endforeach
                    </div>
                </div>

                {{-- Footer / export actions --}}
                <footer class="px-6 py-4 border-t border-line bg-canvas rounded-b-2xl flex items-center justify-between gap-3 shrink-0">
                    <button type="button" @click="open = false"
                            class="h-9 px-4 rounded-lg text-[13px] font-medium text-ink-2 hover:text-ink hover:bg-line-soft transition-colors">
                        Close
                    </button>

                    <div class="flex items-center gap-2">
                        <span x-show="! count" x-cloak class="text-[11.5px] text-danger mr-1">Pick at least one column</span>

                        <a x-bind:href="hrefFor(excelBase)" download @click="open = false"
                           x-bind:tabindex="count ? '0' : '-1'" x-bind:aria-disabled="(! count).toString()"
                           :class="count ? 'hover:brightness-105' : 'opacity-40 pointer-events-none'"
                           class="h-9 px-4 inline-flex items-center gap-2 rounded-lg bg-success text-white text-[13px] font-medium shadow-card transition">
                            <x-icon name="sheet" class="w-4 h-4" /> Excel
                        </a>
                        <a x-bind:href="hrefFor(pdfBase)" target="_blank" rel="noopener" @click="open = false"
                           x-bind:tabindex="count ? '0' : '-1'" x-bind:aria-disabled="(! count).toString()"
                           :class="count ? 'hover:brightness-105' : 'opacity-40 pointer-events-none'"
                           class="h-9 px-4 inline-flex items-center gap-2 rounded-lg bg-danger text-white text-[13px] font-medium shadow-card transition">
                            <x-icon name="file-text" class="w-4 h-4" /> PDF
                        </a>
                    </div>
                </footer>
            </div>
        </div>
    </template>
</div>

@once
    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('exportSelector', (config) => ({
                    open: false,
                    all: config.all,
                    defaults: config.defaults,
                    templates: config.templates,
                    excelBase: config.excelBase,
                    pdfBase: config.pdfBase,
                    selected: [...config.defaults],

                    get total() { return this.all.length; },
                    get count() { return this.selected.length; },

                    selectAll() { this.selected = [...this.all]; },
                    selectNone() { this.selected = []; },

                    // Templates and group toggles rebuild `selected` in definition order so the
                    // sheet's columns never reshuffle based on interaction order.
                    applyTemplate(keys) { this.selected = this.all.filter((k) => keys.includes(k)); },

                    groupAll(keys) { return keys.length > 0 && keys.every((k) => this.selected.includes(k)); },
                    groupSome(keys) {
                        const n = keys.filter((k) => this.selected.includes(k)).length;
                        return n > 0 && n < keys.length;
                    },
                    toggleGroup(keys, on) {
                        const set = new Set(this.selected);
                        keys.forEach((k) => on ? set.add(k) : set.delete(k));
                        this.selected = this.all.filter((k) => set.has(k));
                    },

                    hrefFor(base) {
                        if (! this.selected.length) return '#';
                        // Everything ticked → omit the param; the server already defaults to all.
                        if (this.selected.length === this.all.length) return base;
                        const ordered = this.all.filter((k) => this.selected.includes(k));
                        const sep = base.includes('?') ? '&' : '?';
                        return base + sep + 'columns=' + ordered.join(',');
                    },
                }));
            });
        </script>
    @endpush
@endonce
