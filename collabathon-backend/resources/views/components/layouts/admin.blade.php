@props(['active' => '', 'title' => 'Admin', 'section' => null])

@php
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

// Indexed count on (role, status) — cheap enough to run per request.
$pendingCount = User::role(User::ROLE_BROKER)->status(User::STATUS_PENDING)->count();

// Eager-loaded once so the per-item Gate::allows('view-module', ...) calls below
// don't each re-query role_permissions.
auth()->user()->loadMissing('adminRole.permissions');

$navGroups = [
    'Overview' => [
        ['key' => 'dashboard', 'icon' => 'dashboard', 'label' => 'Dashboard', 'route' => route('admin.dashboard')],
    ],
    'Manage' => [
        ['key' => 'approvals', 'icon' => 'user-check', 'label' => 'Pending Approvals', 'route' => route('admin.approvals'), 'count' => $pendingCount],
        // The roster of brokers already through approval — the queue's output, kept
        // next to it rather than buried as a tab on a page about outstanding work.
        ['key' => 'cp', 'icon' => 'users', 'label' => 'Channel Partners', 'route' => route('admin.cp')],
        ['key' => 'developers', 'icon' => 'building', 'label' => 'Developers', 'route' => route('admin.developers')],
        ['key' => 'properties', 'icon' => 'list', 'label' => 'Projects', 'route' => route('admin.properties')],
        ['key' => 'leads', 'icon' => 'chart', 'label' => 'Approvals', 'route' => route('admin.leads')],
    ],
    'Configure' => [
        ['key' => 'settings', 'icon' => 'cog', 'label' => 'Settings', 'route' => route('admin.settings')],
    ],
];

// Each nav item's `key` matches a Role::MODULES key 1:1 — reused directly as the
// Gate argument, no second naming scheme.
$navGroups = collect($navGroups)
    ->map(fn ($items) => array_values(array_filter(
        $items,
        fn ($item) => Gate::allows('view-module', $item['key'])
    )))
    ->reject(fn ($items) => empty($items))
    ->all();

// Added after filtering so it's never run through the module-based view-module check.
if (Gate::allows('manage-team')) {
    $navGroups['Administration'] = [
        ['key' => 'team', 'icon' => 'users', 'label' => 'Team', 'route' => route('admin.team')],
        ['key' => 'roles', 'icon' => 'shield', 'label' => 'Roles', 'route' => route('admin.roles')],
    ];
}

// Live notifications: the approval backlog plus the most recent unlocked leads.
$notifications = [];

if ($pendingCount > 0) {
    $notifications[] = [
        'icon' => 'user-check', 'tone' => 'info',
        'text' => $pendingCount . ' channel partner ' . Str::plural('registration', $pendingCount) . ' awaiting review',
        'time' => 'Now',
    ];
}

foreach (Lead::where('contact_unlocked', true)
    ->with(['broker:id,name', 'property:id,name'])
    ->latest('interested_at')
    ->limit(3)
    ->get() as $lead) {
    $notifications[] = [
        'icon' => 'trending-up', 'tone' => 'success',
        'text' => ($lead->broker?->name ?? 'A broker') . ' is interested in ' . ($lead->property?->name ?? 'a listing'),
        'time' => $lead->interested_at?->diffForHumans() ?? '',
    ];
}

$toneClasses = [
    'info' => 'bg-info-soft text-info',
    'success' => 'bg-success-soft text-success',
    'danger' => 'bg-danger-soft text-danger',
    'neutral' => 'bg-canvas text-ink-3',
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
{{-- Page itself never scrolls; the sidebar and the main column each own their scroll. --}}
<body class="h-dvh overflow-hidden bg-canvas font-sans antialiased text-ink"
      x-data="{ mobileNav: false, navigating: false }"
      {{--
          Every admin page is a full document load, so the only way to show that a
          navigation is in flight is to catch it on the way out. Capture phase, because
          a row link's own handler may stop propagation before it bubbles to us.

          Skipped for anything that does not replace this document: new tabs, modified
          clicks, downloads, in-page anchors, and javascript:/mailto:/tel: schemes —
          flagging those would leave the skeleton stuck up with no navigation coming.
      --}}
      x-on:click.capture="
          const a = $event.target.closest('a');
          if (!a || $event.defaultPrevented || $event.metaKey || $event.ctrlKey
              || $event.shiftKey || $event.altKey || $event.button !== 0) return;
          if (a.target === '_blank' || a.hasAttribute('download')) return;
          const href = a.getAttribute('href') || '';
          if (!href || href.startsWith('#') || /^(javascript|mailto|tel):/i.test(href)) return;
          if (a.href === window.location.href) return;
          navigating = true;
      "
      x-on:submit.capture="
          if (!$event.defaultPrevented && $event.target.getAttribute('method')?.toLowerCase() !== 'dialog') {
              navigating = true;
          }
      "
      {{-- Restored from bfcache the document is reused, so clear the flag by hand. --}}
      x-on:pageshow.window="navigating = false">

    <div class="flex h-dvh">

        {{-- ============================ Sidebar ============================ --}}
        <aside class="fixed inset-y-0 left-0 z-40 w-[264px] shrink-0 h-dvh bg-nav flex flex-col overflow-hidden
                      transition-transform duration-200 lg:translate-x-0 lg:static lg:z-auto"
               :class="mobileNav ? 'translate-x-0' : '-translate-x-full'">

            <div class="h-[60px] flex items-center gap-2.5 px-5 shrink-0">
                {{-- Brand tile carries the undimmed logo orange with dark ink, the same
                     pairing as the sidebar's other marks — see components/nav-link. --}}
                <div class="w-[30px] h-[30px] rounded-lg bg-primary-light flex items-center justify-center shrink-0">
                    <span class="text-nav font-bold text-[11.5px] tracking-tight">iR</span>
                </div>
                <div class="min-w-0">
                    <p class="text-white font-semibold text-[13.5px] leading-tight tracking-[-0.01em]">iREC Admin</p>
                    <p class="text-nav-text-2 text-[10.5px] leading-tight">Platform control</p>
                </div>
                <button type="button" @click="mobileNav = false"
                        class="ml-auto lg:hidden text-nav-text-2 hover:text-white p-1 -mr-1" aria-label="Close navigation">
                    <x-icon name="x" class="w-4.5 h-4.5" />
                </button>
            </div>

            <nav class="flex-1 px-3 pt-3 overflow-y-auto scrollbar-slim" aria-label="Main">
                @foreach($navGroups as $groupLabel => $items)
                    <p class="px-3.5 pt-4 pb-1.5 text-[10px] font-semibold uppercase tracking-[0.09em] text-nav-text-3">
                        {{ $groupLabel }}
                    </p>
                    <div class="space-y-0.5">
                        @foreach($items as $item)
                            <x-nav-link
                                :icon="$item['icon']"
                                :route="$item['route']"
                                :active="$active === $item['key']"
                                :count="$item['count'] ?? null">
                                {{ $item['label'] }}
                            </x-nav-link>
                        @endforeach
                    </div>
                @endforeach
            </nav>

            <div class="p-3 shrink-0 border-t border-nav-line">
                <div class="flex items-center gap-2.5 px-2 py-2 rounded-lg">
                    <span class="w-8 h-8 rounded-avatar bg-primary-soft-dark text-primary-light ring-1 ring-inset ring-primary-ring-dark
                                 inline-flex items-center justify-center text-[12px] font-semibold shrink-0">
                        {{ Str::upper(Str::substr(auth()->user()->name, 0, 1)) }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-[12.5px] font-medium text-nav-text truncate">{{ auth()->user()->name }}</p>
                        <p class="text-[11px] text-nav-text-2 truncate">{{ auth()->user()->email }}</p>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" class="shrink-0"
                        x-on:submit.prevent="$dispatch('confirm-request', {
                            title: 'Log out?',
                            message: 'You will need to sign in again to get back to the admin panel.',
                            confirmLabel: 'Log out',
                            tone: 'danger',
                            form: $el,
                        })" >
                        @csrf
                        <button type="submit" title="Log out" aria-label="Log out"
                                class="text-nav-text-2 hover:text-nav-text hover:bg-nav-hover rounded-control p-1.5 transition-colors block">
                            <x-icon name="logout" class="w-4 h-4" />
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        {{-- Mobile scrim --}}
        <div x-show="mobileNav" x-cloak @click="mobileNav = false" x-transition.opacity
             class="fixed inset-0 z-30 bg-scrim lg:hidden"></div>

        {{-- ============================ Main ============================ --}}
        <div class="flex-1 min-w-0 h-dvh flex flex-col overflow-hidden">

            {{-- Fixed by layout, not by `sticky` — only <main> below it scrolls. --}}
            <header class="h-[60px] z-20 bg-panel border-b border-line
                           flex items-center gap-4 px-5 lg:px-7 shrink-0">

                <button type="button" @click="mobileNav = true"
                        class="lg:hidden text-ink-2 hover:text-ink p-1.5 -ml-1.5" aria-label="Open navigation">
                    <x-icon name="menu" class="w-5 h-5" />
                </button>

                {{-- Full breadcrumb from sm up; below that the header would otherwise be empty,
                     so the page name stands in for it. --}}
                <nav aria-label="Breadcrumb" class="min-w-0 hidden sm:flex items-center gap-1.5 text-[12.5px]">
                    <span class="text-ink-3">iREC</span>
                    @if($section)
                        <x-icon name="chevron-right" class="w-3.5 h-3.5 text-ink-3 shrink-0" />
                        <span class="text-ink-3">{{ $section }}</span>
                    @endif
                    <x-icon name="chevron-right" class="w-3.5 h-3.5 text-ink-3 shrink-0" />
                    <span class="text-ink font-medium truncate">{{ $title }}</span>
                </nav>
                <p class="sm:hidden min-w-0 truncate text-[14px] font-semibold text-ink tracking-[-0.01em]">{{ $title }}</p>

                <div class="flex items-center gap-2 ml-auto">
                    {{-- Global search --}}
                    <label class="relative hidden md:block">
                        <span class="sr-only">Search the platform</span>
                        <x-icon name="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-ink-3 pointer-events-none" />
                        <input type="search" placeholder="Search…"
                               class="w-[210px] h-9 pl-9 pr-12 rounded-lg bg-canvas border border-transparent text-[13px] text-ink
                                      placeholder:text-ink-3 hover:border-line focus:bg-panel focus:border-primary-ring
                                      focus:outline-none focus:w-[260px] transition-all duration-200">
                        <kbd class="absolute right-2.5 top-1/2 -translate-y-1/2 text-[10px] text-ink-3 bg-panel border border-line
                                    px-1.5 py-0.5 font-sans pointer-events-none">⌘K</kbd>
                    </label>

                    {{-- Notifications --}}
                    <x-dropdown width="w-80">
                        <x-slot:trigger>
                            <button type="button" aria-label="Notifications"
                                    class="relative text-ink-2 hover:text-ink hover:bg-canvas rounded-control p-2 transition-colors">
                                <x-icon name="bell" class="w-[18px] h-[18px]" />
                                <span class="absolute top-1.5 right-1.5 w-2 h-2 rounded-notification bg-danger ring-2 ring-panel"></span>
                            </button>
                        </x-slot:trigger>

                        <div class="px-3 py-2 border-b border-line-soft flex items-center justify-between">
                            <p class="text-[12.5px] font-semibold text-ink">Notifications</p>
                            <span class="text-[11px] text-ink-3 nums">{{ count($notifications) }} new</span>
                        </div>
                        <div class="max-h-72 overflow-y-auto scrollbar-slim py-1">
                            @foreach($notifications as $note)
                                <div class="flex items-start gap-2.5 px-3 py-2.5 hover:bg-canvas transition-colors">
                                    <span class="w-7 h-7 rounded-notification flex items-center justify-center shrink-0 mt-0.5 {{ $toneClasses[$note['tone']] }}">
                                        <x-icon :name="$note['icon']" class="w-3.5 h-3.5" />
                                    </span>
                                    <div class="min-w-0">
                                        <p class="text-[12.5px] text-ink leading-snug">{{ $note['text'] }}</p>
                                        <p class="text-[11px] text-ink-3 mt-0.5">{{ $note['time'] }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="px-3 py-2 border-t border-line-soft">
                            <a href="{{ route('admin.approvals') }}" class="text-[12px] font-medium text-primary-dark hover:underline">
                                Review pending approvals
                            </a>
                        </div>
                    </x-dropdown>

                    <div class="w-px h-5 bg-line mx-0.5"></div>

                    {{-- Account --}}
                    <x-dropdown width="w-52">
                        <x-slot:trigger>
                            <button type="button" class="flex items-center gap-2 rounded-lg pl-1 pr-1.5 py-1 hover:bg-canvas transition-colors">
                                <x-avatar :name="auth()->user()->name" :src="auth()->user()->avatar_path" size="sm" />
                                <x-icon name="chevron-down" class="w-3.5 h-3.5 text-ink-3" />
                            </button>
                        </x-slot:trigger>

                        <div class="px-3 py-2 border-b border-line-soft">
                            <p class="text-[12.5px] font-medium text-ink">{{ auth()->user()->name }}</p>
                            <p class="text-[11.5px] text-ink-3 truncate">{{ auth()->user()->email }}</p>
                        </div>
                        <div class="py-1">
                            <x-dropdown-item icon="cog" tag="a" href="{{ route('admin.settings') }}">Settings</x-dropdown-item>
                            <x-dropdown-item icon="shield">Security</x-dropdown-item>
                        </div>
                        <div class="pt-1 border-t border-line-soft">
                            <form method="POST" action="{{ route('logout') }}"
                                x-on:submit.prevent="$dispatch('confirm-request', {
                            title: 'Log out?',
                            message: 'You will need to sign in again to get back to the admin panel.',
                            confirmLabel: 'Log out',
                            tone: 'danger',
                            form: $el,
                        })" >
                                @csrf
                                <x-dropdown-item icon="logout" tone="danger" tag="button" type="submit">Log out</x-dropdown-item>
                            </form>
                        </div>
                    </x-dropdown>
                </div>
            </header>

            <main class="flex-1 min-h-0 overflow-y-auto scrollbar-slim scroll-smooth">
                <div x-show="navigating" x-cloak aria-busy="true" aria-live="polite">
                    <x-page-skeleton />
                </div>
                <div x-show="! navigating" class="px-5 lg:px-7 py-6">
                    <div class="max-w-[1400px] mx-auto">
                        {{ $slot }}
                    </div>
                </div>
            </main>
        </div>
    </div>

    {{-- Global overlays. Mounted here once, so no page includes any of them itself:
         pages just flash a session key or $dispatch an event. --}}
    <x-toast />
    <x-confirm-dialog />
    <x-credentials-dialog />
    <x-reset-password-dialog />

    @stack('scripts')
</body>
</html>
