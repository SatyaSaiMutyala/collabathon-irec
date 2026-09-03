<x-layouts.admin active="master_data" title="Master Data" section="Manage">

    <x-page-header
        title="Master Data"
        subtitle="Developer and project registrations submitted on irecexpo.com. Open one to see the full submission and convert it into a real developer account here." />

    @unless($apiOk)
        <div class="flex items-start gap-3 rounded-xl bg-danger-soft ring-1 ring-inset ring-danger-ring px-4 py-3 mb-5">
            <x-icon name="x" class="w-4 h-4 text-danger shrink-0 mt-0.5" />
            <div>
                <p class="text-[13px] font-medium text-danger">Couldn't load Master Data</p>
                <p class="text-[12.5px] text-ink-2 mt-0.5 leading-relaxed">{{ $apiError }}</p>
            </div>
        </div>
    @endunless

    <x-data-table
        :paginator="$records"
        label="registrations"
        search-placeholder="Search by company, project or contact…"
        empty-icon="inbox"
        empty-title="No registrations match"
        empty-description="Adjust the search or filters to see more.">

        <x-slot:filters>
            <x-filter-text name="city" placeholder="City" icon="map-pin" />
            <x-filter-text name="dev" placeholder="Developer" icon="building" />
            <x-filter-text name="type" placeholder="Project type" icon="list" />
            <x-filter-text name="bhk" placeholder="BHK" icon="list" />
            <x-filter-select name="status"
                             :options="['approved' => 'Approved', 'pending' => 'Pending', 'rejected' => 'Rejected']"
                             placeholder="Any status" />
        </x-slot:filters>

        <x-slot:head>
            <x-th>Company</x-th>
            <x-th hide="lg">Project</x-th>
            <x-th hide="md">Contact</x-th>
            <x-th hide="xl">City</x-th>
            <x-th align="right">Status</x-th>
        </x-slot:head>

        @foreach($records as $record)
            @php
                $profile = $record['developer_profile'] ?? [];
                $project = $record['project_details'] ?? [];
                $sync = $record['sync_meta'] ?? [];
                $referenceCode = $record['reference_code'] ?? null;
                $developerId = $referenceCode ? ($convertedCodes[$referenceCode] ?? null) : null;
            @endphp
            <tr class="hover:bg-canvas transition-colors cursor-pointer"
                x-on:click="window.location = @js(route('admin.master-data.show', $record['registration_id']))">
                <td class="px-4 py-3">
                    <div class="flex items-center gap-2.5 min-w-0">
                        @if($profile['builder_logo_url'] ?? null)
                            <img src="{{ $profile['builder_logo_url'] }}" alt=""
                                 class="w-8 h-8 rounded-lg object-cover border border-line-soft shrink-0">
                        @else
                            <x-avatar :name="$profile['company_name'] ?? '—'" size="sm" />
                        @endif
                        <div class="min-w-0">
                            <p class="text-[13px] font-medium text-ink truncate">{{ $profile['company_name'] ?? '—' }}</p>
                            <p class="text-[11.5px] text-ink-3 truncate">{{ $referenceCode }}</p>
                        </div>
                    </div>
                </td>

                <td class="px-4 py-3 hidden lg:table-cell">
                    <p class="text-[12.5px] text-ink-2 truncate">{{ $project['project_name'] ?? '—' }}</p>
                    <p class="text-[11.5px] text-ink-3 truncate">{{ $project['project_type'] ?? '' }}</p>
                </td>

                <td class="px-4 py-3 hidden md:table-cell">
                    <p class="text-[12.5px] text-ink-2 truncate">{{ $profile['key_contact_person'] ?? '—' }}</p>
                    <p class="text-[11.5px] text-ink-3 truncate">{{ $profile['email'] ?? '' }}</p>
                </td>

                <td class="px-4 py-3 hidden xl:table-cell">
                    <span class="text-[12.5px] text-ink-2">{{ $profile['city'] ?? '—' }}</span>
                </td>

                <td class="px-4 py-3" data-row-actions>
                    <div class="flex items-center justify-end gap-1.5">
                        @if($developerId)
                            <x-badge tone="success" size="sm" dot>Converted</x-badge>
                        @else
                            <x-badge tone="neutral" size="sm">{{ ucfirst($sync['status'] ?? 'unknown') }}</x-badge>
                        @endif
                    </div>
                </td>
            </tr>
        @endforeach
    </x-data-table>
</x-layouts.admin>
