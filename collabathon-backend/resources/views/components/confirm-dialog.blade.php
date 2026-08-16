{{--
    Global confirmation dialog — replaces window.confirm(), which renders as a raw
    browser alert (unstyled, shows the host name, reads like an error).

    Mounted once in layouts/admin.blade.php. Any form opts in by preventing its own
    submit and dispatching the request:

        <form method="POST" action="…"
              x-on:submit.prevent="$dispatch('confirm-request', {
                  title: 'Delete this role?',
                  message: 'Members assigned to it lose their permissions.',
                  confirmLabel: 'Delete',
                  tone: 'danger',
                  form: $el,
              })">

    On accept the dialog calls form.submit(), which bypasses submit listeners — so the
    handler above cannot re-fire and loop. It also fires `navigate-start` on window,
    the layout's cue to raise the page skeleton: the prevented submit that opened this
    dialog deliberately does not raise it, or cancelling would strand it on screen.
--}}

<div x-data="confirmDialog()"
     x-on:confirm-request.window="request($event.detail)"
     x-show="open" x-cloak
     @keydown.escape.window="cancel()"
     class="fixed inset-0 z-[65] flex items-start justify-center px-4 py-10 overflow-y-auto"
     role="alertdialog" aria-modal="true" aria-labelledby="confirm-title">

    <div x-show="open" x-transition.opacity @click="cancel()" class="fixed inset-0 bg-scrim"></div>

    <div x-show="open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-3 scale-[0.98]"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-[0.98]"
         class="relative bg-panel rounded-2xl w-full max-w-sm shadow-modal my-auto">

        <div class="px-6 pt-6 pb-5">
            <div class="flex items-start gap-3.5">
                <span class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0"
                      :class="tone === 'danger' ? 'bg-danger-soft text-danger' : 'bg-warning-soft text-warning'">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
                         class="w-[18px] h-[18px]" aria-hidden="true">
                        <path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z" />
                        <path d="M12 9v4M12 17h.01" />
                    </svg>
                </span>
                <div class="min-w-0 pt-0.5">
                    <h3 id="confirm-title" class="text-[14.5px] font-semibold text-ink tracking-[-0.01em]" x-text="title"></h3>
                    <p class="text-[12.5px] text-ink-2 leading-relaxed mt-1.5" x-text="message"></p>
                </div>
            </div>
        </div>

        <footer class="px-6 py-4 border-t border-line bg-canvas rounded-b-2xl flex items-center justify-end gap-2.5">
            <button type="button" @click="cancel()"
                    class="h-9 px-4 rounded-lg text-[13px] font-medium text-ink-2 hover:text-ink hover:bg-line-soft transition-colors">
                Cancel
            </button>
            <button type="button" x-ref="accept" @click="accept()"
                    :class="tone === 'danger' ? 'bg-danger hover:brightness-110' : 'bg-nav hover:bg-nav-soft'"
                    class="h-9 px-4 rounded-lg text-[13px] font-medium text-white transition-colors"
                    x-text="confirmLabel"></button>
        </footer>
    </div>
</div>

@once
    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('confirmDialog', () => ({
                    open: false,
                    title: '',
                    message: '',
                    confirmLabel: 'Confirm',
                    tone: 'danger',
                    form: null,

                    request(detail) {
                        this.title = detail.title ?? 'Are you sure?';
                        this.message = detail.message ?? '';
                        this.confirmLabel = detail.confirmLabel ?? 'Confirm';
                        this.tone = detail.tone ?? 'danger';
                        this.form = detail.form ?? null;
                        this.open = true;
                        this.$nextTick(() => this.$refs.accept?.focus());
                    },

                    cancel() {
                        this.open = false;
                        this.form = null;
                    },

                    accept() {
                        const form = this.form;
                        this.open = false;
                        this.form = null;
                        if (!form) return;
                        // The layout ignores the prevented submit that opened this dialog,
                        // so the page skeleton has to be raised by hand for the real one.
                        window.dispatchEvent(new CustomEvent('navigate-start'));
                        // .submit() skips submit listeners, so the dispatching handler
                        // that opened this dialog does not fire again.
                        form.submit();
                    },
                }));
            });
        </script>
    @endpush
@endonce
