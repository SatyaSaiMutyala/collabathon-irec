{{--
    Set-a-new-password dialog — mounted once in layouts/admin.blade.php.

    Any row opens it by naming the account and the endpoint to POST to:

        $dispatch('reset-password', { name: 'Skyline Realty', action: '{{ route(…) }}' })

    The admin types the password (or clicks Generate one); the controller falls back to
    a generated password when the field is left blank, then flashes `credentials` so
    x-credentials-dialog takes over with the share options.
--}}

@php
    // A failed submit redirects back here, so the dialog reopens with the account and
    // the inline error rather than silently discarding the attempt. `_action` carries
    // the row's endpoint, which is otherwise lost on the redirect.
    $reopen = $errors->any() && old('_form') === 'reset-password' ? [
        'name' => old('_member'),
        'action' => old('_action'),
    ] : null;
@endphp

<div x-data="resetPasswordDialog(@js($reopen))"
     x-on:reset-password.window="request($event.detail)"
     x-show="open" x-cloak
     @keydown.escape.window="open = false"
     class="fixed inset-0 z-[55] flex items-start justify-center px-4 py-10 overflow-y-auto"
     role="dialog" aria-modal="true" aria-labelledby="reset-title">

    <div x-show="open" x-transition.opacity @click="open = false" class="fixed inset-0 bg-scrim"></div>

    <div x-show="open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-3 scale-[0.98]"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         class="relative bg-panel rounded-2xl w-full max-w-md shadow-modal my-auto">

        <header class="px-6 py-4 border-b border-line flex items-start justify-between gap-4">
            <div class="min-w-0">
                <h3 id="reset-title" class="text-[15px] font-semibold text-ink tracking-[-0.01em]">Set a new password</h3>
                <p class="text-[12.5px] text-ink-3 mt-0.5">
                    <span x-text="memberName"></span>'s current password stops working immediately.
                </p>
            </div>
            <button type="button" @click="open = false" aria-label="Close dialog"
                    class="text-ink-3 hover:text-ink hover:bg-canvas rounded-lg p-1.5 -m-1 transition-colors shrink-0">
                <x-icon name="x" class="w-4.5 h-4.5" />
            </button>
        </header>

        <form method="POST" :action="action" class="px-6 py-5 space-y-4">
            @csrf
            <input type="hidden" name="_form" value="reset-password">
            <input type="hidden" name="_action" :value="action">
            <input type="hidden" name="_member" :value="memberName">

            <x-password-field name="password" label="New password" required :prefill="false"
                              hint="Shown once after saving so you can share it." />

            <div class="pt-1 flex items-center justify-end gap-2.5">
                <button type="button" @click="open = false"
                        class="h-9 px-4 rounded-lg text-[13px] font-medium text-ink-2 hover:text-ink hover:bg-line-soft transition-colors">
                    Cancel
                </button>
                <x-button variant="gold" tag="button" type="submit" icon="lock">Set password</x-button>
            </div>
        </form>
    </div>
</div>

@once
    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('resetPasswordDialog', (reopen) => ({
                    open: !! reopen,
                    action: reopen?.action ?? '',
                    memberName: reopen?.name ?? '',

                    request(detail) {
                        this.action = detail.action;
                        this.memberName = detail.name;
                        this.open = true;
                    },
                }));
            });
        </script>
    @endpush
@endonce
