@php
use App\Support\AdminMockData;
$pending = AdminMockData::pendingBrokers();
$decisions = AdminMockData::recentDecisions();
@endphp

<x-layouts.admin active="approvals" title="Broker Approvals">
    <x-page-header title="Broker Approvals" subtitle="Review new broker/CP registrations before they can sign in." />

    <x-panel title="Pending Registrations ({{ count($pending) }})" class="mb-6">
        <table class="w-full text-left">
            <thead>
                <tr class="text-[11.5px] uppercase tracking-wide text-text-muted border-b border-border">
                    <th class="px-5 py-3 font-medium">Broker</th>
                    <th class="px-5 py-3 font-medium">Company</th>
                    <th class="px-5 py-3 font-medium">Contact</th>
                    <th class="px-5 py-3 font-medium">RERA</th>
                    <th class="px-5 py-3 font-medium">Segments</th>
                    <th class="px-5 py-3 font-medium">Submitted</th>
                    <th class="px-5 py-3 font-medium text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @foreach($pending as $broker)
                    <tr class="text-[13.5px] text-navy">
                        <td class="px-5 py-3.5 font-medium">{{ $broker['name'] }}</td>
                        <td class="px-5 py-3.5 text-text-secondary">{{ $broker['company'] }}</td>
                        <td class="px-5 py-3.5 text-text-secondary">
                            <p>{{ $broker['mobile'] }}</p>
                            <p class="text-[12px] text-text-muted">{{ $broker['email'] }}</p>
                        </td>
                        <td class="px-5 py-3.5 text-text-secondary">{{ $broker['rera'] }}</td>
                        <td class="px-5 py-3.5 text-text-secondary">{{ implode(', ', $broker['segments']) }}</td>
                        <td class="px-5 py-3.5 text-text-muted">{{ \Illuminate\Support\Carbon::parse($broker['submittedAt'])->format('d M Y') }}</td>
                        <td class="px-5 py-3.5">
                            <div class="flex items-center justify-end gap-2">
                                <x-button variant="success-ghost" size="sm" tag="button" type="button">
                                    <x-icon name="check" class="w-3.5 h-3.5" /> Approve
                                </x-button>
                                <x-button variant="danger-ghost" size="sm" tag="button" type="button">
                                    <x-icon name="x" class="w-3.5 h-3.5" /> Reject
                                </x-button>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-panel>

    <x-panel title="Recent Decisions">
        <div class="divide-y divide-border">
            @foreach($decisions as $d)
                <div class="px-5 py-3.5 flex items-center justify-between">
                    <div>
                        <p class="text-[13.5px] font-medium text-navy">{{ $d['name'] }}</p>
                        <p class="text-[12.5px] text-text-secondary">{{ $d['company'] }}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-[12px] text-text-muted">{{ \Illuminate\Support\Carbon::parse($d['date'])->format('d M Y') }}</span>
                        <x-badge :tone="$d['decision'] === 'approved' ? 'success' : 'danger'">{{ ucfirst($d['decision']) }}</x-badge>
                    </div>
                </div>
            @endforeach
        </div>
    </x-panel>
</x-layouts.admin>
