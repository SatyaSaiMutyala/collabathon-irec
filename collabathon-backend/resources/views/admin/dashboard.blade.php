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

    <x-page-header
        title="Dashboard"
        subtitle="Platform activity across every developer, broker and listing on iREC.">
        <x-slot:actions>
            <x-button variant="outline" icon="download">Export</x-button>
        </x-slot:actions>
    </x-page-header>


    {{-- ---------------------------- KPI row ---------------------------- --}}
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-3.5 mb-5">
        @foreach($stats as $stat)
            <x-stat-card
                :icon="$stat['icon']"
                :label="$stat['label']"
                :value="$stat['value']"
                :good-when-up="$stat['goodWhenUp'] ?? true"
                :spark="$stat['spark']" />
        @endforeach
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

        <x-panel title="Most in-demand listings" subtitle="By broker interests" padded>
            <x-chart-bars :rows="$topProperties" color="var(--color-chart-1)" />
        </x-panel>

        <x-panel title="Recent activity" flush>
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
                               description="Views and interests will appear here as brokers use the app." />
            @endforelse
        </x-panel>

        <x-panel title="Awaiting your review" flush class="lg:col-span-2 xl:col-span-1"
                 :subtitle="$pendingBrokers->count() . ' brokers cannot sign in until approved'">
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
                               description="Every broker registration has been reviewed." />
            @endforelse
        </x-panel>
    </div>
</x-layouts.admin>
