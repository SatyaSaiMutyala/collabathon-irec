@php
    $statusTone = ['active' => 'success', 'draft' => 'warning', 'archived' => 'neutral'];
@endphp

<x-layouts.admin active="properties" title="Properties" section="Manage">

    <x-page-header
        title="Properties"
        subtitle="Every listing on the platform and the developer it belongs to. Brokers only see active listings.">
        <x-slot:actions>
            <x-modal title="Add property" subtitle="The listing goes live to brokers once its status is Active."
                     width="max-w-xl"
                     :open="$errors->any()">
                <x-slot:trigger>
                    <x-button variant="gold" icon="plus">Add property</x-button>
                </x-slot:trigger>

                <form method="POST" action="{{ route('admin.properties.store') }}" class="space-y-4">
                    @csrf
                    <x-field label="Project name" name="name" placeholder="e.g. Azure Bay Residences" required />

                    {{-- A property always belongs to exactly one developer. --}}
                    <x-select-field label="Assign to developer" name="developer_id" required
                                    hint="Leads from this listing route to this developer.">
                        @foreach($developers as $dev)
                            <option value="{{ $dev->id }}" @selected((string) old('developer_id') === (string) $dev->id)>{{ $dev->company_name }}</option>
                        @endforeach
                    </x-select-field>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <x-field label="City" name="city" placeholder="e.g. Dubai" icon="map-pin" required />
                        <x-field label="Locality" name="locality" placeholder="e.g. Dubai Marina" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <x-select-field label="Property type" name="project_type"
                                        :options="['Residential', 'Commercial', 'Mixed-use', 'Plotted Development', 'Villa', 'Row House']" />
                        <x-select-field label="Status" name="listing_status"
                                        :options="['draft' => 'Draft', 'active' => 'Active', 'archived' => 'Archived']" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <x-field label="Price from" name="price_min" type="number" placeholder="1800000" required />
                        <x-field label="Price to" name="price_max" type="number" placeholder="3200000" required />
                    </div>

                    <x-field label="CP commission %" name="cp_commission_percent" type="number" step="0.01" placeholder="2.50" />
                    <x-field label="Description" name="description" type="textarea" placeholder="Short overview of the project" />

                    <div class="pt-1">
                        <x-button variant="gold" tag="button" type="submit" icon="check" class="w-full">Add property</x-button>
                    </div>
                </form>
            </x-modal>
        </x-slot:actions>
    </x-page-header>

    <x-flash />

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3.5 mb-5">
        <x-stat-card icon="list" label="Total listings" :value="$totals['all']" />
        <x-stat-card icon="check" label="Active" :value="$totals['active']" />
        <x-stat-card icon="eye" label="Total views" :value="$totals['views']" />
        <x-stat-card icon="users" label="Total interests" :value="$totals['interests']" />
    </div>

    <x-data-table
        :paginator="$properties"
        label="listings"
        search-placeholder="Search by project, locality or city…"
        empty-title="No listings match"
        empty-description="Adjust the search or filters to see more properties.">

        <x-slot:filters>
            <x-filter-select name="developer_id" :options="$developers->pluck('company_name', 'id')" placeholder="All developers" />
            <x-filter-select name="type"
                             :options="['Residential', 'Commercial', 'Mixed-use', 'Plotted Development', 'Villa', 'Row House']"
                             placeholder="Any type" />
            <x-filter-select name="status" :options="['draft' => 'Draft', 'active' => 'Active', 'archived' => 'Archived']" placeholder="Any status" />
        </x-slot:filters>

        <x-slot:head>
            <x-th sort="name">Project</x-th>
            <x-th hide="lg">Developer</x-th>
            <x-th hide="xl">Location</x-th>
            <x-th hide="xl">Type</x-th>
            <x-th sort="price" hide="md">Price range</x-th>
            <x-th align="right" sort="views" hide="xl">Views</x-th>
            <x-th align="right" sort="interests" hide="lg">Interested</x-th>
            <x-th>Status</x-th>
            <x-th align="right"><span class="sr-only">Actions</span></x-th>
        </x-slot:head>

        @foreach($properties as $p)
            <tr class="hover:bg-canvas/70 transition-colors">
                <td class="px-4 py-3">
                    <p class="text-[13px] font-medium text-ink truncate">{{ $p->name }}</p>
                </td>

                <td class="px-4 py-3 hidden lg:table-cell">
                    <div class="flex items-center gap-2 min-w-0">
                        <x-avatar :name="$p->developer?->company_name ?? '—'" size="xs" />
                        <span class="text-[12.5px] text-ink-2 truncate">{{ $p->developer?->company_name }}</span>
                    </div>
                </td>

                <td class="px-4 py-3 hidden xl:table-cell">
                    <span class="inline-flex items-center gap-1.5 text-[12.5px] text-ink-2">
                        <x-icon name="map-pin" class="w-3.5 h-3.5 text-ink-3 shrink-0" />
                        {{ $p->locality ?: $p->city }}
                    </span>
                </td>

                <td class="px-4 py-3 hidden xl:table-cell">
                    <x-badge tone="neutral" size="sm">{{ $p->project_type }}</x-badge>
                </td>

                <td class="px-4 py-3 text-[12.5px] text-ink-2 nums whitespace-nowrap hidden md:table-cell">
                    {{ $p->currency }} {{ number_format($p->price_min / 1_000_000, 2) }}M – {{ number_format($p->price_max / 1_000_000, 2) }}M
                </td>

                <td class="px-4 py-3 text-right text-[12.5px] text-ink-2 nums hidden xl:table-cell">{{ number_format($p->views_count) }}</td>
                <td class="px-4 py-3 text-right text-[12.5px] font-medium text-ink nums hidden lg:table-cell">{{ $p->interests_count }}</td>

                <td class="px-4 py-3">
                    <x-badge :tone="$statusTone[$p->listing_status] ?? 'neutral'" size="sm" dot>
                        {{ ucfirst($p->listing_status) }}
                    </x-badge>
                </td>

                <td class="px-4 py-3 text-right">
                    <x-dropdown>
                        <x-slot:trigger>
                            <button type="button" aria-label="Actions for {{ $p->name }}"
                                    class="text-ink-3 hover:text-ink hover:bg-canvas rounded-md p-1.5 transition-colors">
                                <x-icon name="dots" class="w-4 h-4" />
                            </button>
                        </x-slot:trigger>

                        <x-dropdown-item icon="eye" tag="a" href="{{ route('admin.leads', ['search' => $p->name]) }}">
                            View leads
                        </x-dropdown-item>

                        <div class="my-1 border-t border-line-soft"></div>

                        @foreach(['draft' => 'Move to draft', 'active' => 'Publish listing', 'archived' => 'Archive listing'] as $value => $label)
                            @if($p->listing_status !== $value)
                                <form method="POST" action="{{ route('admin.properties.update', $p) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="listing_status" value="{{ $value }}">
                                    <x-dropdown-item :icon="$value === 'active' ? 'check' : ($value === 'archived' ? 'x' : 'list')"
                                                     :tone="$value === 'archived' ? 'danger' : 'default'"
                                                     tag="button" type="submit">{{ $label }}</x-dropdown-item>
                                </form>
                            @endif
                        @endforeach
                    </x-dropdown>
                </td>
            </tr>
        @endforeach
    </x-data-table>
</x-layouts.admin>
