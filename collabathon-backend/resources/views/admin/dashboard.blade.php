@php
use App\Support\AdminMockData;
$stats = AdminMockData::dashboardStats();
$activity = AdminMockData::recentActivity();
$pending = array_slice(AdminMockData::pendingBrokers(), 0, 3);
@endphp

<x-layouts.admin active="dashboard" title="Dashboard">
    <x-page-header title="Dashboard" subtitle="Platform overview across every developer and broker on iREC." />

    <div class="grid grid-cols-5 gap-4 mb-7">
        <x-stat-card icon="building" label="Developers" :value="$stats['developers']" />
        <x-stat-card icon="users" label="Brokers" :value="$stats['brokers']" />
        <x-stat-card icon="list" label="Properties" :value="$stats['properties']" />
        <x-stat-card icon="clock" label="Pending Approvals" :value="$stats['pendingApprovals']" />
        <x-stat-card icon="chart" label="Confirmed Matches" :value="$stats['matches']" />
    </div>

    <div class="grid grid-cols-3 gap-5">
        <x-panel title="Recent Activity" class="col-span-2">
            <div class="divide-y divide-border">
                @foreach($activity as $item)
                    <div class="px-5 py-3.5 flex items-start gap-3">
                        <div class="w-8 h-8 rounded-lg bg-surface text-text-secondary flex items-center justify-center shrink-0 mt-0.5">
                            <x-icon :name="$item['icon']" class="w-4 h-4" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-[13.5px] text-navy">{{ $item['text'] }}</p>
                            <p class="text-[12px] text-text-muted mt-0.5">{{ $item['time'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-panel>

        <x-panel title="Awaiting Your Review">
            <x-slot:actions>
                <a href="{{ url('/admin/approvals') }}" class="text-[12.5px] text-primary-dark font-medium hover:underline">View all</a>
            </x-slot:actions>
            <div class="divide-y divide-border">
                @foreach($pending as $broker)
                    <div class="px-5 py-3.5">
                        <p class="text-[13.5px] font-medium text-navy">{{ $broker['name'] }}</p>
                        <p class="text-[12.5px] text-text-secondary mt-0.5">{{ $broker['company'] }} &middot; {{ $broker['city'] }}</p>
                    </div>
                @endforeach
                @if(empty($pending))
                    <p class="px-5 py-6 text-[13px] text-text-muted text-center">No pending registrations.</p>
                @endif
            </div>
        </x-panel>
    </div>
</x-layouts.admin>
