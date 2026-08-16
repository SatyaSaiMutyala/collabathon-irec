{{-- Step 1 of 3 — see Admin\PasswordResetController. --}}
<x-layouts.guest title="Reset password — iREC Admin">
    <x-auth.shell
        heading="Reset your password"
        :subheading="'Enter the email on your admin account and we\'ll send a ' . \App\Models\PasswordResetOtp::CODE_LENGTH . '-digit code to that inbox.'"
        step="1">

        <form action="{{ route('password.email') }}" method="POST" class="space-y-4">
            @csrf

            <x-field label="Email" name="email" type="email" icon="mail"
                     value="{{ old('email') }}" placeholder="admin@irec.ae" required autofocus />

            <x-button variant="gold" size="lg" tag="button" type="submit" class="w-full mt-1" icon-right="arrow-right">
                Send code
            </x-button>
        </form>

        <x-slot:footer>
            <a href="{{ route('login') }}"
               class="inline-flex items-center gap-1.5 text-[12.5px] text-ink-2 hover:text-ink transition-colors">
                <x-icon name="chevron-left" class="w-3.5 h-3.5" />
                Back to sign in
            </a>
        </x-slot:footer>
    </x-auth.shell>
</x-layouts.guest>
