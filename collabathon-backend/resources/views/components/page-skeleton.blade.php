{{--
    The placeholder shown while the *next* admin page is being fetched.

    This panel is server-rendered, so there is no per-component loading state to shimmer —
    a page's HTML arrives complete or not at all. The one real wait is navigation: a
    sidebar link or a filter/pagination link costs a full round trip (~0.5s on the
    dashboard queries), and until it lands the browser leaves the *old* page on screen
    with no sign anything is happening.

    So the skeleton is page-shaped, not component-shaped: header, a stat row, a toolbar
    and a table — the layout almost every admin screen resolves to. Mounted once in
    layouts/admin.blade.php and revealed by the `navigating` flag there.
--}}
<div class="px-5 lg:px-7 py-6">
    <div class="max-w-[1400px] mx-auto">

        {{-- Page title --}}
        <x-skeleton w="w-56" h="h-6" />
        <x-skeleton w="w-full max-w-md" h="h-3" class="mt-3" />

        {{-- Stat cards --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mt-6">
            @for($i = 0; $i < 4; $i++)
                <div class="bg-panel border border-line p-4">
                    <x-skeleton w="w-24" h="h-3" />
                    <x-skeleton w="w-16" h="h-7" class="mt-3" />
                </div>
            @endfor
        </div>

        {{-- Toolbar --}}
        <div class="flex items-center gap-3 mt-6">
            <x-skeleton w="w-full max-w-sm" h="h-9" />
            <x-skeleton w="w-32" h="h-9" />
            <x-skeleton w="w-24" h="h-9" class="ml-auto hidden sm:block" />
        </div>

        {{-- Table --}}
        <div class="bg-panel border border-line mt-4">
            <div class="px-4 py-3 border-b border-line-soft flex gap-4">
                <x-skeleton w="w-32" h="h-3" />
                <x-skeleton w="w-40" h="h-3" class="hidden md:block" />
                <x-skeleton w="w-24" h="h-3" class="hidden lg:block" />
                <x-skeleton w="w-20" h="h-3" class="ml-auto" />
            </div>
            @for($i = 0; $i < 8; $i++)
                <div class="px-4 py-3.5 flex items-center gap-4 border-b border-line-soft last:border-b-0">
                    <x-skeleton w="w-9" h="h-9" class="rounded-avatar shrink-0" />
                    <div class="flex-1 min-w-0">
                        <x-skeleton w="w-40" h="h-3" />
                        <x-skeleton w="w-28" h="h-2.5" class="mt-2" />
                    </div>
                    <x-skeleton w="w-40" h="h-3" class="hidden md:block" />
                    <x-skeleton w="w-24" h="h-3" class="hidden lg:block" />
                    <x-skeleton w="w-20" h="h-7" class="ml-auto" />
                </div>
            @endfor
        </div>
    </div>
</div>
