{{--
    Credentials hand-off dialog — mounted once in layouts/admin.blade.php.

    Opens automatically when a controller flashes `credentials`:

        ->with('credentials', ['name' => …, 'email' => …, 'password' => …])

    Any row can also open it on demand for an existing account:

        $dispatch('share-credentials', { name: …, email: …, resetAction: '…' })

    That second path carries no password — stored passwords are hashed and can never be
    read back, so sharing one means setting a new one. Pass `resetAction` (the row's
    reset-password endpoint) and the dialog offers that as a one-click step instead of
    leaving the reader to work out why the password is missing.
--}}

<div x-data="credentialsDialog(@js(session('credentials')), @js(url('/login')))"
     x-on:share-credentials.window="share($event.detail)"
     x-show="open" x-cloak
     @keydown.escape.window="open = false"
     class="fixed inset-0 z-[55] flex items-start justify-center px-4 py-10 overflow-y-auto"
     role="dialog" aria-modal="true" aria-labelledby="cred-title">

    <div x-show="open" x-transition.opacity @click="open = false" class="fixed inset-0 bg-scrim"></div>

    <div x-show="open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-3 scale-[0.98]"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         class="relative bg-panel rounded-2xl w-full max-w-md shadow-modal my-auto">

        <header class="px-6 py-4 border-b border-line flex items-start justify-between gap-4">
            <div class="min-w-0">
                <h3 id="cred-title" class="text-[15px] font-semibold text-ink tracking-[-0.01em]"
                    x-text="hasPassword ? 'Credentials ready' : 'Share sign-in details'"></h3>
                <p class="text-[12.5px] text-ink-3 mt-0.5">
                    <span x-show="hasPassword">
                        Share these with <span x-text="cred.name"></span> — the password is not shown again.
                    </span>
                    <span x-show="! hasPassword" x-cloak>
                        Send <span x-text="cred.name"></span> their sign-in link. Their existing
                        password is stored hashed and cannot be shown again.
                    </span>
                </p>
            </div>
            <button type="button" @click="open = false" aria-label="Close dialog"
                    class="text-ink-3 hover:text-ink hover:bg-canvas rounded-lg p-1.5 -m-1 transition-colors shrink-0">
                <x-icon name="x" class="w-4.5 h-4.5" />
            </button>
        </header>

        <div class="px-6 py-5 space-y-3">
            <div class="rounded-xl bg-canvas ring-1 ring-inset ring-line divide-y divide-line-soft">
                <div class="flex items-center gap-3 px-3.5 py-2.5">
                    <span class="text-[11.5px] text-ink-3 w-[68px] shrink-0">Email</span>
                    <span class="text-[12.5px] text-ink font-mono truncate flex-1" x-text="cred.email"></span>
                    <button type="button" @click="copy(cred.email, 'Email copied')"
                            class="text-[11.5px] font-medium text-primary-dark hover:underline shrink-0">Copy</button>
                </div>
                <div class="flex items-center gap-3 px-3.5 py-2.5" x-show="hasPassword">
                    <span class="text-[11.5px] text-ink-3 w-[68px] shrink-0">Password</span>
                    <span class="text-[12.5px] text-ink font-mono truncate flex-1" x-text="cred.password"></span>
                    <button type="button" @click="copy(cred.password, 'Password copied')"
                            class="text-[11.5px] font-medium text-primary-dark hover:underline shrink-0">Copy</button>
                </div>
                <div class="flex items-center gap-3 px-3.5 py-2.5">
                    <span class="text-[11.5px] text-ink-3 w-[68px] shrink-0">Sign in</span>
                    <span class="text-[12.5px] text-ink font-mono truncate flex-1" x-text="loginUrl"></span>
                    <button type="button" @click="copy(loginUrl, 'Link copied')"
                            class="text-[11.5px] font-medium text-primary-dark hover:underline shrink-0">Copy</button>
                </div>
            </div>

            {{-- Without a password there is nothing worth sharing yet, so the primary
                 action becomes "set one" rather than a link-only copy. --}}
            <button type="button" x-show="! hasPassword && !! resetAction" x-cloak
                    @click="setPassword()"
                    class="w-full flex items-center justify-center gap-2 h-10 rounded-lg bg-primary text-white
                           text-[13px] font-medium hover:bg-primary-dark transition-colors">
                <x-icon name="lock" class="w-4 h-4" />
                Set a password to share
            </button>

            <button type="button" @click="copy(message(), 'Sign-in details copied')"
                    class="w-full flex items-center justify-center gap-2 h-10 rounded-lg bg-nav text-white
                           text-[13px] font-medium hover:bg-nav-soft transition-colors">
                <x-icon name="download" class="w-4 h-4" />
                <span x-text="hasPassword ? 'Copy full sign-in details' : 'Copy sign-in link only'"></span>
            </button>

            <div>
                <p class="text-[11.5px] text-ink-3 mb-2">Share via</p>
                <div class="grid grid-cols-3 gap-2">
                    <a :href="`https://wa.me/?text=${encodeURIComponent(message())}`"
                       target="_blank" rel="noopener"
                       class="flex flex-col items-center justify-center gap-1 h-16 rounded-lg ring-1 ring-inset ring-line
                              hover:bg-canvas transition-colors">
                        <svg viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5 text-success" aria-hidden="true">
                            <path d="M17.5 14.4c-.3-.2-1.7-.9-2-1-.3-.1-.4-.2-.6.1s-.7.9-.9 1.1c-.2.2-.3.2-.6.1a8 8 0 0 1-2.4-1.5 9 9 0 0 1-1.6-2c-.2-.3 0-.5.1-.6l.5-.5.3-.5v-.5l-.9-2.1c-.2-.5-.4-.5-.6-.5h-.6c-.2 0-.5.1-.8.4-.3.3-1 1-1 2.5s1.1 2.9 1.2 3.1c.2.2 2.1 3.2 5 4.4 1.9.8 2.6.9 3.5.8.6-.1 1.7-.7 2-1.4.2-.7.2-1.3.2-1.4l-.5-.3Z"/>
                            <path d="M12 2a10 10 0 0 0-8.5 15.3L2 22l4.8-1.5A10 10 0 1 0 12 2Zm0 18.2c-1.6 0-3.2-.4-4.5-1.3l-.3-.2-2.9.9.9-2.8-.2-.3A8.2 8.2 0 1 1 12 20.2Z"/>
                        </svg>
                        <span class="text-[11px] text-ink-2">WhatsApp</span>
                    </a>

                    <a :href="mailtoHref()"
                       class="flex flex-col items-center justify-center gap-1 h-16 rounded-lg ring-1 ring-inset ring-line
                              hover:bg-canvas transition-colors">
                        <x-icon name="mail" class="w-5 h-5 text-info" />
                        <span class="text-[11px] text-ink-2">Email</span>
                    </a>

                    {{-- Native share sheet (mobile / supporting browsers); hidden otherwise. --}}
                    <button type="button" @click="nativeShare()" x-show="canShare"
                            class="flex flex-col items-center justify-center gap-1 h-16 rounded-lg ring-1 ring-inset ring-line
                                   hover:bg-canvas transition-colors">
                        <x-icon name="external" class="w-5 h-5 text-ink-2" />
                        <span class="text-[11px] text-ink-2">More…</span>
                    </button>

                    <button type="button" @click="copy(message(), 'Sign-in details copied')" x-show="! canShare"
                            class="flex flex-col items-center justify-center gap-1 h-16 rounded-lg ring-1 ring-inset ring-line
                                   hover:bg-canvas transition-colors">
                        <x-icon name="list" class="w-5 h-5 text-ink-2" />
                        <span class="text-[11px] text-ink-2">Copy</span>
                    </button>
                </div>
            </div>
        </div>

        <footer class="px-6 py-4 border-t border-line bg-canvas rounded-b-2xl">
            <x-button variant="gold" tag="button" type="button" x-on:click="open = false" class="w-full">
                Done
            </x-button>
        </footer>
    </div>
</div>

@once
    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('credentialsDialog', (initial, loginUrl) => ({
                    open: false,
                    // Placeholder keys keep x-text bindings from throwing before first open.
                    cred: initial ?? { name: '', email: '', password: null },
                    resetAction: '',
                    loginUrl,
                    canShare: typeof navigator !== 'undefined' && !! navigator.share,

                    init() {
                        // A flashed payload means an account was just created or its
                        // password reset — show it straight away.
                        if (initial) this.open = true;
                    },

                    get hasPassword() {
                        return !! this.cred.password;
                    },

                    // Opened from a row: email + link only, never a password.
                    share(detail) {
                        this.cred = { name: detail.name, email: detail.email, password: null };
                        this.resetAction = detail.resetAction ?? '';
                        this.open = true;
                    },

                    // Hand off to the reset dialog; saving there flashes `credentials`,
                    // which reopens this dialog with the new password and full share options.
                    setPassword() {
                        const detail = { name: this.cred.name, action: this.resetAction };
                        this.open = false;
                        window.dispatchEvent(new CustomEvent('reset-password', { detail }));
                    },

                    message() {
                        const lines = [
                            `Hi ${this.cred.name}, your iREC account is ready.`,
                            '',
                            `Sign in: ${this.loginUrl}`,
                            `Email: ${this.cred.email}`,
                        ];

                        if (this.hasPassword) {
                            lines.push(`Password: ${this.cred.password}`, '', 'Please change your password after signing in.');
                        } else {
                            lines.push('', 'Use your existing password, or ask an admin to reset it.');
                        }

                        return lines.join('\n');
                    },

                    mailtoHref() {
                        const subject = encodeURIComponent('Your iREC account');
                        return `mailto:${encodeURIComponent(this.cred.email)}?subject=${subject}&body=${encodeURIComponent(this.message())}`;
                    },

                    async copy(text, label) {
                        try {
                            await navigator.clipboard.writeText(text);
                            window.toast?.(label, 'success');
                        } catch {
                            window.toast?.('Could not copy — copy it manually.', 'warning');
                        }
                    },

                    async nativeShare() {
                        try {
                            await navigator.share({ title: 'iREC account', text: this.message() });
                        } catch (e) {
                            // A user-cancelled share sheet is not an error worth reporting.
                            if (e?.name !== 'AbortError') window.toast?.('Sharing failed.', 'error');
                        }
                    },
                }));
            });
        </script>
    @endpush
@endonce
