{{--
    Step 3 of 3 — see Admin\PasswordResetController.

    Plain JS rather than Alpine for the reveal toggle: Alpine is loaded from a CDN by the
    admin layout only, and the guest layout deliberately has no such dependency. This is
    the screen someone reaches *because* they are locked out, so it should not be one
    blocked CDN away from being unusable. Same reasoning in auth.verify-code.
--}}
<x-layouts.guest title="New password — iREC Admin">
    <x-auth.shell
        heading="Choose a new password"
        subheading="Setting a new password for {{ $masked }}. You'll be signed out everywhere else."
        step="3">

        <form action="{{ route('password.update') }}" method="POST" class="space-y-4">
            @csrf

            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label for="password" class="text-[12.5px] font-medium text-ink">
                        New password <span class="text-danger" aria-hidden="true">*</span>
                    </label>
                    {{-- One toggle for both fields: confirming a password you cannot see
                         while the first one is visible is pure friction. --}}
                    <button type="button" id="reveal-toggle" tabindex="-1"
                            class="text-[11.5px] font-medium text-primary-dark hover:underline">
                        Show
                    </button>
                </div>
                <div class="relative">
                    <x-icon name="lock" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-ink-3 pointer-events-none" />
                    <input id="password" name="password" type="password" data-reveal
                           placeholder="At least 8 characters" required
                           minlength="8" maxlength="72" autocomplete="new-password" autofocus
                           @class([
                               'w-full h-10 pl-9 pr-3.5 rounded-lg bg-panel border text-[13.5px] text-ink font-mono',
                               'placeholder:font-sans placeholder:text-ink-3',
                               'focus:outline-none focus:ring-[3px] transition-[border-color,box-shadow]',
                               'border-danger focus:border-danger focus:ring-danger-ring' => $errors->has('password'),
                               'border-line focus:border-primary focus:ring-primary-ring' => ! $errors->has('password'),
                           ])>
                </div>
                <p class="text-[11.5px] text-ink-3 mt-1.5">Minimum 8 characters.</p>
            </div>

            <div>
                <label for="password_confirmation" class="text-[12.5px] font-medium text-ink mb-1.5 block">
                    Confirm password <span class="text-danger" aria-hidden="true">*</span>
                </label>
                <div class="relative">
                    <x-icon name="lock" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-ink-3 pointer-events-none" />
                    <input id="password_confirmation" name="password_confirmation" type="password" data-reveal
                           placeholder="Re-enter the password" required
                           minlength="8" maxlength="72" autocomplete="new-password"
                           class="w-full h-10 pl-9 pr-3.5 rounded-lg bg-panel border border-line text-[13.5px] text-ink font-mono
                                  placeholder:font-sans placeholder:text-ink-3 focus:outline-none focus:border-primary
                                  focus:ring-[3px] focus:ring-primary-ring transition-[border-color,box-shadow]">
                </div>
            </div>

            <x-button variant="gold" size="lg" tag="button" type="submit" class="w-full mt-1" icon-right="arrow-right">
                Update password
            </x-button>
        </form>

        <x-slot:footer>
            <a href="{{ route('login') }}"
               class="inline-flex items-center gap-1.5 text-[12.5px] text-ink-2 hover:text-ink transition-colors">
                <x-icon name="chevron-left" class="w-3.5 h-3.5" />
                Cancel and sign in
            </a>
        </x-slot:footer>
    </x-auth.shell>

    <script>
        (function () {
            const toggle = document.getElementById('reveal-toggle');
            const fields = document.querySelectorAll('[data-reveal]');

            toggle.addEventListener('click', function () {
                const reveal = fields[0].type === 'password';
                fields.forEach(f => { f.type = reveal ? 'text' : 'password'; });
                toggle.textContent = reveal ? 'Hide' : 'Show';
            });
        })();
    </script>
</x-layouts.guest>
