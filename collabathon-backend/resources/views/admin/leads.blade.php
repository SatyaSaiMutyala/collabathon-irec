@php
use App\Support\AdminMockData;
$leads = AdminMockData::leadsAndMatches();
$toneMap = ['viewed' => 'neutral', 'interested' => 'warning', 'accepted' => 'success', 'declined' => 'danger'];
@endphp

<x-layouts.admin active="leads" title="Leads & Matches">
    <x-page-header title="Leads & Matches" subtitle="Every view and interest across all developers and properties, platform-wide." />

    <div class="bg-primary-soft border border-primary-light/50 rounded-xl px-5 py-3.5 mb-6 text-[13px] text-navy/80">
        <strong class="text-navy">Note:</strong> a broker's contact details only unlock once they mark a property "Interested" — casual views never expose contact info.
    </div>

    <x-panel title="All Activity ({{ count($leads) }})">
        <table class="w-full text-left">
            <thead>
                <tr class="text-[11.5px] uppercase tracking-wide text-text-muted border-b border-border">
                    <th class="px-5 py-3 font-medium">Broker</th>
                    <th class="px-5 py-3 font-medium">Property</th>
                    <th class="px-5 py-3 font-medium">Developer</th>
                    <th class="px-5 py-3 font-medium">Date</th>
                    <th class="px-5 py-3 font-medium">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @foreach($leads as $lead)
                    <tr class="text-[13.5px] text-navy">
                        <td class="px-5 py-3.5 font-medium">{{ $lead['broker'] }}</td>
                        <td class="px-5 py-3.5 text-text-secondary">{{ $lead['property'] }}</td>
                        <td class="px-5 py-3.5 text-text-secondary">{{ $lead['developer'] }}</td>
                        <td class="px-5 py-3.5 text-text-muted">{{ \Illuminate\Support\Carbon::parse($lead['date'])->format('d M Y') }}</td>
                        <td class="px-5 py-3.5">
                            <x-badge :tone="$toneMap[$lead['status']]">{{ ucfirst($lead['status']) }}</x-badge>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-panel>
</x-layouts.admin>
