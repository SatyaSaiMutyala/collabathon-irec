<x-layouts.guest title="Sign in — iREC Admin">
    <div class="min-h-screen grid lg:grid-cols-2">

        {{-- ------------------------ Brand panel ------------------------ --}}
        <div class="relative hidden lg:flex flex-col justify-between p-12 overflow-hidden bg-nav">
            <div class="relative flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-primary flex items-center justify-center">
                    <span class="text-white font-bold text-[12px]">iR</span>
                </div>
                <span class="text-white font-semibold text-[14.5px] tracking-[-0.01em]">iREC</span>
            </div>

            <div class="relative max-w-md">
                <h2 class="text-white text-[26px] font-semibold leading-snug tracking-[-0.02em]">
                    One place to approve brokers, issue developer accounts and watch every lead.
                </h2>
                <p class="text-nav-text-2 text-[13.5px] mt-4 leading-relaxed">
                    Brokers register from the mobile app and stay locked out until you approve them.
                    Developers never self-register — you create their accounts here.
                </p>

                <div class="flex items-center gap-8 mt-10 pt-8 border-t border-nav-line">
                    @foreach([['58', 'Brokers'], ['5', 'Developers'], ['640', 'Leads tracked']] as [$value, $label])
                        <div>
                            <p class="text-white text-[20px] font-semibold leading-none">{{ $value }}</p>
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

                <div class="flex items-center gap-2.5 mb-9 lg:hidden">
                    <div class="w-8 h-8 rounded-lg bg-primary flex items-center justify-center">
                        <span class="text-white font-bold text-[12px]">iR</span>
                    </div>
                    <span class="text-ink font-semibold text-[14.5px]">iREC Admin</span>
                </div>

                <div class="mb-7">
                    <h1 class="text-[22px] font-semibold text-ink tracking-[-0.02em]">Welcome back</h1>
                    <p class="text-[13px] text-ink-2 mt-1.5">Sign in to manage developers, brokers and projects.</p>
                </div>

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

                <form action="{{ route('login') }}" method="POST" class="space-y-4">
                    @csrf

                    {{-- Email is the login key for every role, admin included. --}}
                    <x-field label="Email" name="email" type="email" icon="mail"
                             value="{{ old('email') }}" placeholder="admin@irec.ae" required autofocus />

                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label for="password" class="text-[12.5px] font-medium text-ink">Password</label>
                            <a href="#" class="text-[11.5px] text-primary-dark hover:underline">Forgot?</a>
                        </div>
                        <div class="relative">
                            <x-icon name="lock" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-ink-3 pointer-events-none" />
                            <input id="password" name="password" type="password" placeholder="Enter password" required
                                   class="w-full h-10 pl-9 pr-3.5 rounded-lg bg-panel border border-line text-[13.5px] text-ink
                                          placeholder:text-ink-3 focus:outline-none focus:border-primary
                                          focus:ring-[3px] focus:ring-primary-ring transition-[border-color,box-shadow]">
                        </div>
                    </div>

                    <label class="flex items-center gap-2 select-none cursor-pointer">
                        <input type="checkbox" name="remember" value="1"
                               class="w-4 h-4 rounded border-line text-primary focus:ring-primary-ring">
                        <span class="text-[12.5px] text-ink-2">Keep me signed in</span>
                    </label>

                    <x-button variant="gold" size="lg" tag="button" type="submit" class="w-full mt-1" icon-right="arrow-right">
                        Sign in
                    </x-button>
                </form>

                <div class="flex items-center gap-2.5 mt-8 rounded-lg bg-panel border border-line px-3.5 py-3">
                    <x-icon name="shield" class="w-4 h-4 text-ink-3 shrink-0" />
                    <p class="text-[11.5px] text-ink-3 leading-relaxed">
                        Single admin access. All approval decisions are recorded against this account.
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-layouts.guest>
