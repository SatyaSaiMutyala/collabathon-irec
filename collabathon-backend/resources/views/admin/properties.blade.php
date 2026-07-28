@php
use App\Support\AdminMockData;
$properties = AdminMockData::properties();
$developers = AdminMockData::developers();
@endphp

<x-layouts.admin active="properties" title="Properties">
    <x-page-header title="Properties" subtitle="Add listings and assign each one to a developer.">
        <x-slot:actions>
            <x-modal title="Add Property">
                <x-slot:trigger>
                    <x-button variant="gold" tag="button" type="button">
                        <x-icon name="plus" class="w-4 h-4" /> Add Property
                    </x-button>
                </x-slot:trigger>

                <form class="space-y-4">
                    <x-field label="Project Name" placeholder="Enter project name" />
                    <div>
                        <label class="block text-[12.5px] font-medium text-navy mb-1.5">Assign to Developer</label>
                        <select class="w-full px-3.5 py-2.5 rounded-lg border border-border text-[13.5px] text-navy focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary">
                            @foreach($developers as $dev)
                                <option>{{ $dev['company'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <x-field label="City / Locality" placeholder="Enter location" />
                        <x-field label="Property Type" placeholder="Residential / Commercial" />
                    </div>
                    <x-field label="Price Range" placeholder="e.g. AED 1.2M – 2.4M" />
                    <x-button variant="gold" tag="button" type="button" class="w-full mt-1">
                        Add Property
                    </x-button>
                </form>
            </x-modal>
        </x-slot:actions>
    </x-page-header>

    <x-panel title="All Properties ({{ count($properties) }})">
        <table class="w-full text-left">
            <thead>
                <tr class="text-[11.5px] uppercase tracking-wide text-text-muted border-b border-border">
                    <th class="px-5 py-3 font-medium">Project</th>
                    <th class="px-5 py-3 font-medium">Developer</th>
                    <th class="px-5 py-3 font-medium">Location</th>
                    <th class="px-5 py-3 font-medium">Price Range</th>
                    <th class="px-5 py-3 font-medium">Interested</th>
                    <th class="px-5 py-3 font-medium">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @foreach($properties as $p)
                    <tr class="text-[13.5px] text-navy">
                        <td class="px-5 py-3.5 font-medium">{{ $p['name'] }}</td>
                        <td class="px-5 py-3.5 text-text-secondary">{{ $p['developer'] }}</td>
                        <td class="px-5 py-3.5 text-text-secondary">{{ $p['city'] }}</td>
                        <td class="px-5 py-3.5 text-text-secondary">{{ $p['price'] }}</td>
                        <td class="px-5 py-3.5 text-text-secondary">{{ $p['interested'] }}</td>
                        <td class="px-5 py-3.5">
                            <x-badge :tone="$p['status'] === 'active' ? 'success' : 'neutral'">{{ ucfirst($p['status']) }}</x-badge>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-panel>
</x-layouts.admin>
