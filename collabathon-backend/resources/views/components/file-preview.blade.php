{{--
    Global file/image preview overlay — what every uploaded brochure, floor plan, scan and
    gallery image opens into instead of a new browser tab.

    A new tab is the wrong place to check an attachment. It loses the record you were
    reading, the back button lands you on a re-queried page, and on a signed S3 link the
    tab title is a hash rather than a filename — so an admin verifying six documents ends
    up with six anonymous tabs and no idea which is which.

    Mounted once in layouts/admin.blade.php, same as x-confirm-dialog. Nothing opens it
    directly: <x-preview-link> wraps whatever you want clickable and dispatches the event.

        <x-preview-link :url="$url" :path="$item->path" name="Brochure">View</x-preview-link>

    Sets navigate together. Give several links the same `group` and the overlay collects
    them from the DOM when one is opened, so the arrows (and the left/right keys) step
    through the whole gallery rather than closing and reopening per image. Collecting at
    open time, rather than each link carrying a copy of the list, is what keeps a
    twenty-image gallery from embedding the same twenty-entry payload twenty times.
--}}

<div x-data="filePreview()"
     x-on:file-preview.window="show($event.detail)"
     x-show="open" x-cloak
     @keydown.escape.window="close()"
     @keydown.arrow-left.window="prev()"
     @keydown.arrow-right.window="next()"
     {{-- Above confirm-dialog (z-65): a preview opened from anywhere is the thing in
          front, and nothing else is ever meant to sit on top of it. --}}
     class="fixed inset-0 z-[70] flex flex-col"
     role="dialog" aria-modal="true" aria-label="File preview">

    <div x-show="open" x-transition.opacity @click="close()" class="absolute inset-0 bg-scrim"></div>

    {{-- Header ------------------------------------------------------------- --}}
    <header class="relative flex items-center gap-3 px-4 sm:px-6 py-3 shrink-0">
        <div class="min-w-0 flex-1">
            <p class="text-[13px] font-medium text-white truncate" x-text="current?.name || 'Preview'"></p>
            <p class="text-[11.5px] text-white/60 nums" x-show="items.length > 1">
                <span x-text="index + 1"></span> of <span x-text="items.length"></span>
            </p>
        </div>

        {{-- Open and Download stay available for everything, including the kinds shown
             inline: a floor plan is often wanted at full size or on paper, and the
             overlay is a quick look rather than a replacement for the file itself. --}}
        <a x-bind:href="current?.url" target="_blank" rel="noopener"
           class="inline-flex items-center gap-1.5 h-8 px-3 rounded-lg text-[12.5px] font-medium
                  text-white/80 hover:text-white hover:bg-white/10 transition-colors">
            <x-icon name="external" class="w-3.5 h-3.5" />
            <span class="hidden sm:inline">Open</span>
        </a>
        <a x-bind:href="current?.url" x-bind:download="current?.name || true"
           class="inline-flex items-center gap-1.5 h-8 px-3 rounded-lg text-[12.5px] font-medium
                  text-white/80 hover:text-white hover:bg-white/10 transition-colors">
            <x-icon name="download" class="w-3.5 h-3.5" />
            <span class="hidden sm:inline">Download</span>
        </a>
        <button type="button" @click="close()" aria-label="Close preview"
                class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-white/80
                       hover:text-white hover:bg-white/10 transition-colors shrink-0">
            <x-icon name="x" class="w-4.5 h-4.5" />
        </button>
    </header>

    {{-- Stage --------------------------------------------------------------- --}}
    <div class="relative flex-1 min-h-0 flex items-center gap-2 px-2 sm:px-4 pb-4">

        <button type="button" @click="prev()" x-show="items.length > 1" aria-label="Previous file"
                class="inline-flex items-center justify-center w-9 h-9 rounded-full bg-white/10 text-white
                       hover:bg-white/20 transition-colors shrink-0">
            <x-icon name="chevron-left" class="w-5 h-5" />
        </button>

        {{-- stop: a click on the file itself must not reach the scrim behind it and close
             the overlay — which is what made dragging to scroll a long PDF dismiss it. --}}
        <div class="relative flex-1 min-w-0 h-full flex items-center justify-center" @click.stop>

            <template x-if="current?.kind === 'image'">
                <img x-bind:src="current.url" x-bind:alt="current.name || ''"
                     class="max-w-full max-h-full object-contain rounded-lg shadow-modal">
            </template>

            {{-- A PDF goes to the browser's own viewer, which brings paging, zoom, search
                 and print with it — none of which is worth rebuilding here. --}}
            <template x-if="current?.kind === 'pdf'">
                <iframe x-bind:src="current.url" x-bind:title="current.name || 'Document'"
                        class="w-full h-full rounded-lg bg-panel border-0"></iframe>
            </template>

            <template x-if="current?.kind === 'video'">
                <video x-bind:src="current.url" controls playsinline
                       class="max-w-full max-h-full rounded-lg shadow-modal"></video>
            </template>

            {{-- Spreadsheets, Word files, archives. Saying so plainly beats an empty frame
                 that looks like the file failed to load. --}}
            <template x-if="current && current.kind === 'file'">
                <div class="bg-panel rounded-2xl shadow-modal px-6 py-7 max-w-sm text-center">
                    <span class="inline-flex items-center justify-center w-11 h-11 rounded-xl bg-canvas text-ink-3 mb-3">
                        <x-icon name="file-text" class="w-5 h-5" />
                    </span>
                    <p class="text-[13.5px] font-medium text-ink break-all" x-text="current.name || 'Attachment'"></p>
                    <p class="text-[12.5px] text-ink-2 leading-relaxed mt-1.5">
                        This file type can't be shown here. Open or download it to view the contents.
                    </p>
                    <a x-bind:href="current.url" target="_blank" rel="noopener"
                       class="inline-flex items-center gap-1.5 h-9 px-4 mt-4 rounded-lg bg-nav hover:bg-nav-soft
                              text-[13px] font-medium text-white transition-colors">
                        <x-icon name="external" class="w-3.5 h-3.5" /> Open file
                    </a>
                </div>
            </template>
        </div>

        <button type="button" @click="next()" x-show="items.length > 1" aria-label="Next file"
                class="inline-flex items-center justify-center w-9 h-9 rounded-full bg-white/10 text-white
                       hover:bg-white/20 transition-colors shrink-0">
            <x-icon name="chevron-right" class="w-5 h-5" />
        </button>
    </div>
</div>

@once
    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('filePreview', () => ({
                    open: false,
                    items: [],
                    index: 0,

                    get current() {
                        return this.items[this.index] ?? null;
                    },

                    show(detail) {
                        if (!detail) return;

                        // A grouped link brings its whole set with it. The nodes are read
                        // in document order, so the arrows follow the order on the page.
                        const group = detail.group
                            ? [...document.querySelectorAll('[data-preview-group="' + detail.group + '"]')]
                            : [];

                        if (group.length) {
                            this.items = group
                                .map((node) => {
                                    try {
                                        return JSON.parse(node.dataset.previewItem);
                                    } catch (error) {
                                        return null;
                                    }
                                })
                                .filter(Boolean);

                            // indexOf on the node itself, not on a url match: two rows can
                            // legitimately point at the same file, and matching by value
                            // would jump to the first of them.
                            const at = group.indexOf(detail.el);
                            this.index = at >= 0 ? at : 0;
                        } else {
                            this.items = [{
                                url: detail.url,
                                name: detail.name ?? null,
                                kind: detail.kind ?? 'file',
                            }];
                            this.index = 0;
                        }

                        if (!this.items.length || !this.current?.url) return;

                        this.open = true;
                    },

                    close() {
                        this.open = false;
                        // Cleared on close so a reopened overlay never paints the previous
                        // file for a frame before the new one loads.
                        this.items = [];
                        this.index = 0;
                    },

                    // Wrapping rather than clamping: at the end of a gallery the next arrow
                    // is still there, and stopping dead on it reads as a broken button.
                    next() {
                        if (this.open && this.items.length > 1) {
                            this.index = (this.index + 1) % this.items.length;
                        }
                    },

                    prev() {
                        if (this.open && this.items.length > 1) {
                            this.index = (this.index - 1 + this.items.length) % this.items.length;
                        }
                    },
                }));
            });
        </script>
    @endpush
@endonce
