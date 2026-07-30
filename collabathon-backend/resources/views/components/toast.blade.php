{{--
    Global snackbar stack — bottom-right, one per flashed message.

    Mounted once in layouts/admin.blade.php, so every admin page gets it for free;
    pages must NOT include it themselves. Controllers just flash a session key:

        return redirect()->route('admin.team')->with('success', 'Saved.');
        ->with('error', '…')   ->with('warning', '…')   ->with('info', '…')

    'status' is treated as success so the existing controllers keep working.
    Validation failures raise one error toast; the per-field detail is rendered
    inline by x-field / x-select-field.

    From JS:  window.toast('Copied to clipboard', 'success')
--}}

@php
    $toasts = [];

    foreach (['success', 'status'] as $key) {
        if (session($key)) {
            $toasts[] = ['tone' => 'success', 'message' => session($key)];
            break; // 'status' is only a legacy alias for 'success' — never show both.
        }
    }

    foreach (['error' => 'error', 'warning' => 'warning', 'info' => 'info'] as $key => $tone) {
        if (session($key)) {
            $toasts[] = ['tone' => $tone, 'message' => session($key)];
        }
    }

    if ($errors->any()) {
        $toasts[] = [
            'tone' => 'error',
            'message' => $errors->count() === 1
                ? $errors->first()
                : $errors->count() . ' fields need attention — see the highlighted inputs.',
        ];
    }
@endphp

<div x-data="toastStack(@js($toasts))"
     class="fixed bottom-5 right-5 z-[60] flex flex-col-reverse gap-2.5 w-[min(24rem,calc(100vw-2.5rem))]"
     role="status" aria-live="polite">

    <template x-for="t in items" :key="t.id">
        <div x-show="t.visible"
             x-transition:enter="transition ease-out duration-250"
             x-transition:enter-start="opacity-0 translate-y-3 sm:translate-x-6 sm:translate-y-0"
             x-transition:enter-end="opacity-100 translate-y-0 sm:translate-x-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-x-0"
             x-transition:leave-end="opacity-0 translate-x-6"
             @mouseenter="pause(t)" @mouseleave="resume(t)"
             class="relative overflow-hidden rounded-xl shadow-modal ring-1 ring-black/10"
             :class="tone(t).wrap">

            <div class="flex items-start gap-2.5 px-4 py-3">
                {{-- x-html, not <template x-if>. Inside <svg> the parser is in foreign-content
                     mode, so a <template> there becomes an SVG element rather than an
                     HTMLTemplateElement — it has no .content, and x-if throws "Cannot read
                     properties of undefined (reading 'cloneNode')". That error aborted Alpine
                     for the rest of the page, taking the toast and anything after it with it. --}}
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"
                     class="w-4 h-4 shrink-0 mt-px" aria-hidden="true"
                     x-html="tone(t).icon"></svg>

                <p class="text-[12.5px] leading-relaxed flex-1 min-w-0" x-text="t.message"></p>

                <button type="button" @click="dismiss(t)" aria-label="Dismiss"
                        class="shrink-0 -mr-1 -mt-0.5 p-1 rounded-md opacity-70 hover:opacity-100 transition-opacity">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2.2" stroke-linecap="round" class="w-3.5 h-3.5" aria-hidden="true">
                        <path d="M18 6 6 18M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{-- Time-remaining bar; pauses while the pointer is over the toast. --}}
            <div class="h-[3px] bg-black/15">
                <div class="h-full bg-current opacity-40 origin-left"
                     :style="`transform: scaleX(${t.progress}); transition: transform 100ms linear`"></div>
            </div>
        </div>
    </template>
</div>

@once
    @push('scripts')
        <script>
            // Written out in full so Tailwind's source scanner sees each class literally.
            // `icon` is the <svg> inner markup, injected with x-html — see the note above it.
            const TOAST_TONES = {
                success: {
                    wrap: 'bg-toast-success text-white',
                    icon: '<path d="M20 6 9 17l-5-5"/>',
                },
                error: {
                    wrap: 'bg-toast-error text-white',
                    icon: '<circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16.5h.01"/>',
                },
                // True yellow needs dark text — white on #f5c518 is unreadable.
                warning: {
                    wrap: 'bg-toast-warning text-ink',
                    icon: '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/>',
                },
                info: {
                    wrap: 'bg-toast-info text-white',
                    icon: '<circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 7.5h.01"/>',
                },
            };

            const TOAST_DURATION = 5000;

            document.addEventListener('alpine:init', () => {
                Alpine.data('toastStack', (initial = []) => ({
                    items: [],
                    seq: 0,

                    init() {
                        initial.forEach((t) => this.push(t.message, t.tone));
                        // Single entry point for any script on the page.
                        window.toast = (message, tone = 'success') => this.push(message, tone);
                    },

                    tone(t) {
                        return TOAST_TONES[t.tone] ?? TOAST_TONES.info;
                    },

                    push(message, tone = 'success') {
                        const item = {
                            id: ++this.seq,
                            message,
                            tone,
                            visible: true,
                            progress: 1,
                            remaining: TOAST_DURATION,
                            timer: null,
                        };

                        this.items.push(item);
                        this.start(item);
                        return item.id;
                    },

                    start(item) {
                        const tick = 100;
                        item.timer = setInterval(() => {
                            item.remaining -= tick;
                            item.progress = Math.max(0, item.remaining / TOAST_DURATION);
                            if (item.remaining <= 0) this.dismiss(item);
                        }, tick);
                    },

                    // Hovering holds the toast open so a long message stays readable.
                    pause(item) {
                        clearInterval(item.timer);
                        item.timer = null;
                    },

                    resume(item) {
                        if (! item.timer && item.visible) this.start(item);
                    },

                    dismiss(item) {
                        clearInterval(item.timer);
                        item.visible = false;
                        // Outlasts the 200ms leave transition before the node is removed.
                        setTimeout(() => {
                            this.items = this.items.filter((i) => i.id !== item.id);
                        }, 250);
                    },
                }));
            });
        </script>
    @endpush
@endonce
