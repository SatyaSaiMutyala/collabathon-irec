@php
$series = [
    ['key' => 'views',     'label' => 'Views',     'color' => 'var(--color-chart-1)'],
    ['key' => 'interests', 'label' => 'Interests', 'color' => 'var(--color-chart-2)'],
];

$activityTones = [
    'info'    => 'bg-info-soft text-info',
    'success' => 'bg-success-soft text-success',
    'danger'  => 'bg-danger-soft text-danger',
    'neutral' => 'bg-canvas text-ink-3',
];

$viewed = $funnel[0]['value'] ?: 0;
$accepted = $funnel[2]['value'] ?: 0;
@endphp

<x-layouts.admin active="dashboard" title="Dashboard" section="Overview">

    {{-- Dashboard-only: every card/container here (KPI tiles, the drill-down panel,
         the chart panels, "Recent activity"/"Awaiting your review") gets a 4px corner
         instead of the rest of the app's deliberately square ones — see the scoped
         `.dashboard-rounded-cards` rule in app.css. Wrapping the whole page rather than
         each component keeps this a one-page opt-in, not a change to `rounded-xl`
         itself, which every other admin page also uses. --}}
    <div class="dashboard-rounded-cards">

    <x-page-header
        title="Dashboard"
        subtitle="Platform activity across every developer, channel partner and listing on iREC.">
        <x-slot:actions>
            <x-button variant="outline" icon="download">Export</x-button>
        </x-slot:actions>
    </x-page-header>


    {{-- ---------------------------- KPI row ---------------------------- --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3.5 mb-5">
        @foreach($stats as $stat)
            @if(!empty($stat['route']))
                {{-- The one tile that is a drill-down rather than a number to read —
                     hover lifts it, a press eases it back down, so the click reads as
                     "opening" this card rather than an instant page swap. Slowed to
                     300ms with an ease-out curve to match the panel's own reveal. --}}
                {{-- `h-full` on both: the grid stretches this <a> to the row's height
                     automatically, but a plain block child does not inherit that — the
                     card itself has to be told to fill it, or a tile with slightly less
                     content (e.g. "Total actions" has no coloured icon badge) renders
                     its bordered box shorter than its siblings instead of matching them. --}}
                <a href="{{ $stat['route'] }}" class="block h-full">
                    <x-stat-card
                        :icon="$stat['icon']"
                        :label="$stat['label']"
                        :value="$stat['value']"
                        :good-when-up="$stat['goodWhenUp'] ?? true"
                        :color="$stat['color'] ?? null"
                        :spark="$stat['spark'] ?? []"
                        data-kpi-tile="{{ $stat['key'] }}"
                        :class="(request('panel') === $stat['key'] ? 'border-primary-ring shadow-md ' : '')
                            . 'h-full hover:border-primary-ring hover:shadow-md hover:-translate-y-0.5
                               active:translate-y-0 active:shadow-card transition-all duration-300 ease-out cursor-pointer'" />
                </a>
            @else
                <x-stat-card
                    :icon="$stat['icon']"
                    :label="$stat['label']"
                    :value="$stat['value']"
                    :good-when-up="$stat['goodWhenUp'] ?? true"
                    :color="$stat['color'] ?? null"
                    :spark="$stat['spark']"
                    class="h-full" />
            @endif
        @endforeach
    </div>

    {{-- ------------------- KPI drill-down panel ------------------- --}}
    {{-- `#panel-wrap` is the AJAX target in resources/js/app.js: clicking a tile above
         fetches admin/dashboard/panel and swaps this div's contents in place, so the
         row list opens right below the cards without a page reload. The real
         `?panel=…` href stays underneath as the no-JS/deep-link fallback — with
         JavaScript off this still works exactly as a normal link + full reload. --}}
    <div id="panel-wrap">
        @include('admin.partials.dashboard-panel')
    </div>

    {{-- ------------------- Trend + funnel ------------------- --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 mb-4">
        <x-panel
            title="Engagement over time"
            subtitle="Listing views and the interests they converted into, per week"
            class="xl:col-span-2"
            padded>
            <x-chart-trend :points="$trend" :series="$series" :height="250" />
        </x-panel>

        <x-panel title="Conversion funnel" subtitle="All recorded activity" padded>
            <div class="pt-1">
                <x-chart-funnel :stages="$funnel" />
            </div>

            <div class="mt-5 pt-4 border-t border-line-soft flex items-center justify-between">
                <div>
                    <p class="text-[11.5px] text-ink-3">View → accepted</p>
                    <p class="text-[19px] font-semibold text-ink mt-0.5 tracking-[-0.01em]">
                        {{ $viewed > 0 ? number_format($accepted / $viewed * 100, 1) : '0.0' }}%
                    </p>
                </div>
                <x-icon name="trending-up" class="w-5 h-5 text-success" />
            </div>
        </x-panel>
    </div>

    {{-- ------------------- Ranked + activity + queue ------------------- --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-4">

        <x-panel title="Most in-demand listings" subtitle="By channel partner interests" padded>
            <x-chart-bars :rows="$topProperties" color="var(--color-chart-1)" />
        </x-panel>

        <x-panel title="Recent activity" flush>
            <x-slot:actions>
                <a href="{{ route('admin.activity') }}" class="text-[11.5px] font-medium text-primary-dark hover:underline">
                    View all
                </a>
            </x-slot:actions>
            @forelse($activity as $item)
                <div class="px-5 py-3 flex items-start gap-3 border-b border-line-soft last:border-0">
                    <span class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 mt-0.5 {{ $activityTones[$item['tone']] }}">
                        <x-icon :name="$item['icon']" class="w-3.5 h-3.5" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-[12.5px] text-ink-2 leading-snug [&_strong]:font-medium [&_strong]:text-ink">
                            {!! $item['text'] !!}
                        </p>
                        <p class="text-[11px] text-ink-3 mt-0.5">{{ $item['time'] }}</p>
                    </div>
                </div>
            @empty
                <x-empty-state icon="inbox" title="No activity yet"
                               description="Views and interests will appear here as channel partners use the app." />
            @endforelse
        </x-panel>

        <x-panel title="Awaiting your review" flush class="lg:col-span-2 xl:col-span-1"
                 :subtitle="$pendingBrokers->count() . ' channel partners cannot sign in until approved'">
            <x-slot:actions>
                <a href="{{ route('admin.approvals') }}"
                   class="text-[12px] font-medium text-primary-dark hover:underline whitespace-nowrap">Review all</a>
            </x-slot:actions>

            @forelse($pendingBrokers as $broker)
                <div class="px-5 py-3 flex items-center gap-3 border-b border-line-soft last:border-0">
                    <x-avatar :name="$broker->name" size="sm" />
                    <div class="min-w-0 flex-1">
                        <p class="text-[12.5px] font-medium text-ink truncate">{{ $broker->name }}</p>
                        <p class="text-[11.5px] text-ink-3 truncate">
                            {{ $broker->brokerProfile?->company_name ?: 'Independent' }}
                            @if($broker->brokerProfile?->city) · {{ $broker->brokerProfile->city }} @endif
                        </p>
                    </div>
                    <span class="text-[11px] text-ink-3 shrink-0 nums">{{ $broker->created_at->format('d M') }}</span>
                </div>
            @empty
                <x-empty-state icon="check" title="Queue is clear"
                               description="Every channel partner registration has been reviewed." />
            @endforelse
        </x-panel>
    </div>

    </div>
</x-layouts.admin>
