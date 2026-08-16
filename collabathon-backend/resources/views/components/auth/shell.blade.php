@props([
    'heading',
    'subheading' => null,
    'step' => null,      // '1' | '2' | '3' — the reset flow's progress dots
    'footer' => null,
])

{{--
    The two-column guest chrome the password-reset screens share.

    Lifted out of auth.login rather than each screen re-declaring it: three near-copies
    of the brand panel would drift apart the first time a stat or a line of copy changes.
    The login page keeps its own copy — it has a different form footer (the trust note)
    and no step indicator, so folding it in here would mean two more props for one caller.
--}}
<div class="min-h-screen grid lg:grid-cols-2">

    {{-- ------------------------ Brand panel ------------------------ --}}
    <div class="relative hidden lg:flex flex-col justify-between p-12 overflow-hidden bg-nav">
        <a href="{{ route('login') }}" class="relative flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-primary flex items-center justify-center">
                <span class="text-white font-bold text-[12px]">iR</span>
            </div>
            <span class="text-white font-semibold text-[14.5px] tracking-[-0.01em]">iREC</span>
        </a>

        <div class="relative max-w-md">
            <h2 class="text-white text-[26px] font-semibold leading-snug tracking-[-0.02em]">
                Locked out? Your inbox is the way back in.
            </h2>
            <p class="text-nav-text-2 text-[13.5px] mt-4 leading-relaxed">
                We email a short code to the address on your admin account. Enter it here and you can
                set a new password — no support ticket, no waiting.
            </p>

            <div class="flex items-center gap-8 mt-10 pt-8 border-t border-nav-line">
                @foreach([['1', 'Enter your email'], ['2', 'Enter the code'], ['3', 'New password']] as [$n, $label])
                    <div>
                        <p @class([
                            'text-[20px] font-semibold leading-none',
                            'text-primary' => $step === $n,
                            'text-white' => $step !== $n,
                        ])>{{ $n }}</p>
                        <p class="text-nav-text-3 text-[11.5px] mt-1.5">{{ $label }}</p>
                    </div>
                @endforeach
            </div>
        </div>

        <p class="relative text-nav-text-3 text-[11.5px]">© {{ date('Y') }} iREC Platform</p>
    </div>

    {{-- ------------------------ Form panel ------------------------ --}}
    <div class="flex items-center justify-center px-5 py-12 bg-canvas">
        <div class="w-full max-w-[368px]">

            <a href="{{ route('login') }}" class="flex items-center gap-2.5 mb-9 lg:hidden">
                <div class="w-8 h-8 rounded-lg bg-primary flex items-center justify-center">
                    <span class="text-white font-bold text-[12px]">iR</span>
                </div>
                <span class="text-ink font-semibold text-[14.5px]">iREC Admin</span>
            </a>

            <div class="mb-7">
                <h1 class="text-[22px] font-semibold text-ink tracking-[-0.02em]">{{ $heading }}</h1>
                @if($subheading)
                    <p class="text-[13px] text-ink-2 mt-1.5 leading-relaxed">{{ $subheading }}</p>
                @endif
            </div>

            @if(session('status'))
                <div class="flex items-start gap-2.5 rounded-lg bg-success-soft ring-1 ring-inset ring-success-ring px-3.5 py-3 mb-4">
                    <x-icon name="check" class="w-4 h-4 text-success shrink-0 mt-0.5" />
                    <p class="text-[12.5px] text-ink-2 leading-relaxed">{{ session('status') }}</p>
                </div>
            @endif

            @if($errors->any())
                <div class="flex items-start gap-2.5 rounded-lg bg-danger-soft ring-1 ring-inset ring-danger-ring px-3.5 py-3 mb-4">
                    <x-icon name="x" class="w-4 h-4 text-danger shrink-0 mt-0.5" />
                    <div class="text-[12.5px] text-ink-2 leading-relaxed">
                        @foreach($errors->all() as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </div>
                </div>
            @endif

            {{ $slot }}

            {{-- Set well clear of the submit button and centred under it: sitting close and
                 left-aligned read as part of the form rather than as the way out of it. --}}
            @if($footer)
                <div class="mt-10 pt-6 border-t border-line flex justify-center">{{ $footer }}</div>
            @endif
        </div>
    </div>
</div>
