@props(['active' => '', 'title' => 'Admin'])

@php
use App\Support\AdminMockData;
$pendingCount = count(AdminMockData::pendingBrokers());

$nav = [
    ['key' => 'dashboard', 'icon' => 'dashboard', 'label' => 'Dashboard', 'route' => url('/admin/dashboard')],
    ['key' => 'approvals', 'icon' => 'users', 'label' => 'Broker Approvals', 'route' => url('/admin/approvals'), 'count' => $pendingCount],
    ['key' => 'developers', 'icon' => 'building', 'label' => 'Developers', 'route' => url('/admin/developers')],
    ['key' => 'properties', 'icon' => 'list', 'label' => 'Properties', 'route' => url('/admin/properties')],
    ['key' => 'leads', 'icon' => 'chart', 'label' => 'Leads & Matches', 'route' => url('/admin/leads')],
    ['key' => 'settings', 'icon' => 'cog', 'label' => 'Settings', 'route' => url('/admin/settings')],
];
@endphp

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — iREC Admin</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://unpkg.com/alpinejs@3.14.1/dist/cdn.min.js"></script>
</head>
<body class="min-h-screen bg-surface font-sans antialiased text-navy">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <aside class="w-[248px] shrink-0 bg-navy flex flex-col">
            <div class="h-16 flex items-center gap-2.5 px-5">
                <div class="w-8 h-8 rounded-lg bg-primary flex items-center justify-center">
                    <span class="text-navy font-bold text-[12px]">iR</span>
                </div>
                <span class="text-white font-semibold text-[14.5px] tracking-wide">iREC Admin</span>
            </div>

            <nav class="flex-1 px-3 mt-4 space-y-1">
                @foreach($nav as $item)
                    <x-nav-link :icon="$item['icon']" :route="$item['route']" :active="$active === $item['key']">
                        {{ $item['label'] }}
                        @if(!empty($item['count']))
                            <span class="ml-auto bg-primary text-navy text-[10.5px] font-semibold px-1.5 py-0.5 rounded-full">{{ $item['count'] }}</span>
                        @endif
                    </x-nav-link>
                @endforeach
            </nav>

            <div class="px-3 pb-4">
                <a href="{{ url('/login') }}" class="flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-[13.5px] font-medium text-white/55 hover:text-white/90 hover:bg-white/5 transition-colors">
                    <x-icon name="logout" class="w-[18px] h-[18px]" />
                    Log Out
                </a>
            </div>
        </aside>

        <!-- Main -->
        <div class="flex-1 min-w-0 flex flex-col">
            <header class="h-16 border-b border-border flex items-center justify-between px-8 bg-white">
                <p class="text-[13px] text-text-secondary">iREC Platform</p>
                <div class="flex items-center gap-5">
                    <button class="relative text-text-secondary hover:text-navy transition-colors">
                        <x-icon name="bell" class="w-5 h-5" />
                        <span class="absolute -top-1 -right-1 w-3.5 h-3.5 rounded-full bg-danger text-white text-[8.5px] flex items-center justify-center">3</span>
                    </button>
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-full bg-primary-soft text-primary-dark flex items-center justify-center text-[12.5px] font-semibold">A</div>
                        <div class="leading-tight">
                            <p class="text-[13px] font-medium text-navy">Admin</p>
                        </div>
                    </div>
                </div>
            </header>

            <main class="flex-1 px-8 py-7">
                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
