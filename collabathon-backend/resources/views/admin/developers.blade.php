@php
use App\Support\AdminMockData;
$developers = AdminMockData::developers();
@endphp

<x-layouts.admin active="developers" title="Developers">
    <x-page-header title="Developers" subtitle="Accounts you've created. Developers never self-register — credentials are issued here.">
        <x-slot:actions>
            <x-modal title="Create Developer Account">
                <x-slot:trigger>
                    <x-button variant="gold" tag="button" type="button">
                        <x-icon name="plus" class="w-4 h-4" /> Add Developer
                    </x-button>
                </x-slot:trigger>

                <form class="space-y-4">
                    <x-field label="Company Name" placeholder="Enter company name" />
                    <x-field label="Contact Person" placeholder="Enter contact person's name" />
                    <div class="grid grid-cols-2 gap-3">
                        <x-field label="Mobile Number" placeholder="+971 5X XXX XXXX" />
                        <x-field label="City" placeholder="Enter city" />
                    </div>
                    <x-field label="Email" placeholder="Enter email address" type="email" />
                    <x-field label="CP Payout %" placeholder="e.g. 2.5%" />
                    <x-button variant="gold" tag="button" type="button" class="w-full mt-1">
                        Create &amp; Generate Credentials
                    </x-button>
                </form>
            </x-modal>
        </x-slot:actions>
    </x-page-header>

    <x-panel title="All Developers ({{ count($developers) }})">
        <table class="w-full text-left">
            <thead>
                <tr class="text-[11.5px] uppercase tracking-wide text-text-muted border-b border-border">
                    <th class="px-5 py-3 font-medium">Company</th>
                    <th class="px-5 py-3 font-medium">Contact</th>
                    <th class="px-5 py-3 font-medium">City</th>
                    <th class="px-5 py-3 font-medium">CP Payout</th>
                    <th class="px-5 py-3 font-medium">Properties</th>
                    <th class="px-5 py-3 font-medium">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @foreach($developers as $dev)
                    <tr class="text-[13.5px] text-navy">
                        <td class="px-5 py-3.5 font-medium">{{ $dev['company'] }}</td>
                        <td class="px-5 py-3.5 text-text-secondary">
                            <p>{{ $dev['contact'] }}</p>
                            <p class="text-[12px] text-text-muted">{{ $dev['mobile'] }}</p>
                        </td>
                        <td class="px-5 py-3.5 text-text-secondary">{{ $dev['city'] }}</td>
                        <td class="px-5 py-3.5 text-text-secondary">{{ $dev['cpPayout'] }}</td>
                        <td class="px-5 py-3.5 text-text-secondary">{{ $dev['properties'] }}</td>
                        <td class="px-5 py-3.5">
                            <x-badge tone="success">Active</x-badge>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-panel>
</x-layouts.admin>
