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
$interested = $funnel[1]['value'] ?: 0;
$accepted = $funnel[2]['value'] ?: 0;

// The funnel's three stages are strict subsets of one another (accepted <= interested
// <= viewed), so turning them into donut segments means the *exclusive* slice each
// lead ended up in — not the raw nested counts, which wouldn't sum to a meaningful
// total. Same 3-step ramp chart-funnel.blade.php uses elsewhere, so a lead's stage
// reads as the same colour wherever it's shown on this page.
$funnelSegments = [
    ['label' => 'Viewed only',     'value' => max($viewed - $interested, 0), 'color' => 'var(--color-funnel-1)'],
    ['label' => 'Interested only', 'value' => max($interested - $accepted, 0), 'color' => 'var(--color-funnel-2)'],
    ['label' => 'Accepted',        'value' => $accepted,                     'color' => 'var(--color-funnel-3)'],
];

$conversionRings = [
    ['label' => 'Interest rate', 'value' => $viewed > 0 ? $interested / $viewed * 100 : 0, 'color' => 'var(--color-funnel-1)'],
    ['label' => 'Accept rate',   'value' => $interested > 0 ? $accepted / $interested * 100 : 0, 'color' => 'var(--color-funnel-2)'],
    ['label' => 'Overall',       'value' => $viewed > 0 ? $accepted / $viewed * 100 : 0, 'color' => 'var(--color-funnel-3)'],
];
@endphp

<x-layouts.admin active="dashboard" title="Dashboard" section="Overview">

    <x-page-header
        title="Dashboard"
        subtitle="Platform activity across every developer, channel partner and listing on iREC.">
        <x-slot:actions>
            {{-- Carries the current URL, so an open KPI panel (and its search) exports
                 alongside the summary — see DashboardController::exportSections(). --}}
            <x-export-menu />
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
                            . 'h-full hover:border-primary-ring hover:shadow-md
                               transition-[border-color,box-shadow] duration-200 ease-out cursor-pointer'" />
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

        <x-panel title="Lead funnel" subtitle="Where every recorded lead ended up" padded>
            <x-chart-donut :segments="$funnelSegments" center-label="Total leads" />

            <div class="mt-5 pt-4 border-t border-line-soft flex items-center justify-center gap-3">
                @foreach($conversionRings as $ring)
                    <x-chart-progress-ring :value="$ring['value']" :label="$ring['label']" :color="$ring['color']" :size="72" :stroke="7" />
                @endforeach
            </div>
        </x-panel>
    </div>

    {{-- ------------------- Ranked + activity + queue ------------------- --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-4">

        <x-panel title="Most in-demand listings" subtitle="By channel partner interests" padded>
            <x-chart-bar-vertical :rows="$topProperties" color="var(--color-chart-1)" :height="180" />
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
                    <x-avatar :name="$broker->name" :src="$broker->brokerProfile?->photo_path" size="sm" />
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
</x-layouts.admin>
