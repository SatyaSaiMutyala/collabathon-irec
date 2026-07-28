<x-layouts.guest title="Sign In — iREC Admin">
    <div class="min-h-screen flex items-center justify-center px-4 relative overflow-hidden">
        <div class="absolute -top-40 -right-40 w-96 h-96 rounded-full bg-primary/10 blur-3xl"></div>
        <div class="absolute -bottom-32 -left-32 w-96 h-96 rounded-full bg-primary/5 blur-3xl"></div>

        <div class="w-full max-w-[380px] relative">
            <div class="flex items-center gap-2.5 mb-10 justify-center">
                <div class="w-9 h-9 rounded-lg bg-primary flex items-center justify-center">
                    <span class="text-navy font-bold text-sm">iR</span>
                </div>
                <span class="text-white font-semibold tracking-wide">iREC Admin</span>
            </div>

            <div class="bg-white rounded-2xl px-7 py-8 shadow-2xl shadow-black/30">
                <h1 class="text-[19px] font-semibold text-navy">Welcome back</h1>
                <p class="text-[13px] text-text-secondary mt-1 mb-7">Sign in to manage developers, brokers &amp; properties.</p>

                <form action="{{ url('/admin/dashboard') }}" method="GET" class="space-y-4">
                    <div>
                        <label class="block text-[12.5px] font-medium text-navy mb-1.5">Mobile Number</label>
                        <div class="relative">
                            <x-icon name="phone" class="w-[17px] h-[17px] absolute left-3.5 top-1/2 -translate-y-1/2 text-text-muted" />
                            <input type="text" placeholder="Enter mobile number" value="+971 50 000 1122"
                                class="w-full pl-10 pr-3.5 py-2.5 rounded-lg border border-border text-[13.5px] text-navy placeholder:text-text-muted focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[12.5px] font-medium text-navy mb-1.5">Password</label>
                        <div class="relative">
                            <x-icon name="lock" class="w-[17px] h-[17px] absolute left-3.5 top-1/2 -translate-y-1/2 text-text-muted" />
                            <input type="password" placeholder="Enter password" value="••••••••"
                                class="w-full pl-10 pr-3.5 py-2.5 rounded-lg border border-border text-[13.5px] text-navy placeholder:text-text-muted focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary">
                        </div>
                    </div>

                    <x-button variant="gold" tag="button" type="submit" class="w-full mt-2">
                        Sign In
                    </x-button>
                </form>
            </div>

            <p class="text-center text-white/35 text-[12px] mt-6">Single Admin access &middot; iREC Platform</p>
        </div>
    </div>
</x-layouts.guest>
