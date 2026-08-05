@php
    $statusTone = ['active' => 'success', 'draft' => 'warning', 'archived' => 'neutral'];

    // The developer's response to the assignment. `pending` is the state that needs
    // chasing, so it is the one that gets a warning tone rather than a neutral one.
    $devTone = ['accepted' => 'success', 'pending' => 'warning', 'declined' => 'danger'];
    $devLabel = ['accepted' => 'Accepted', 'pending' => 'Awaiting', 'declined' => 'Declined'];
@endphp

<x-layouts.admin active="properties" title="Projects" section="Manage">

    <x-page-header
        title="Projects"
        subtitle="Every listing and the developer it belongs to. A project reaches channel partners only once it is Active AND the developer has accepted it.">
        <x-slot:actions>
            {{-- The full project sheet is ~70 fields across nine sections — too much for a
                 modal, so intake lives on its own page. --}}
            <x-button variant="gold" icon="plus" tag="a" href="{{ route('admin.properties.create') }}">
                Add project
            </x-button>
        </x-slot:actions>
    </x-page-header>


    <div class="grid grid-cols-2 md:grid-cols-4 gap-3.5 mb-5">
        <x-stat-card icon="list" label="Total projects" :value="$totals['all']" />
        <x-stat-card icon="check" label="Live to partners" :value="$totals['live']" />
        <x-stat-card icon="clock" label="Awaiting developer" :value="$totals['awaiting']" :good-when-up="false" />
        <x-stat-card icon="x" label="Declined" :value="$totals['declined']" :good-when-up="false" />
    </div>

    <x-data-table
        :paginator="$properties"
        label="listings"
        search-placeholder="Search by project, locality or city…"
        empty-title="No listings match"
        empty-description="Adjust the search or filters to see more projects.">

        <x-slot:filters>
            <x-filter-select name="developer_id" :options="$developers->pluck('company_name', 'id')" placeholder="All developers" />
            <x-filter-select name="type"
                             :options="$projectTypes"
                             placeholder="Any type" />
            <x-filter-select name="project_status"
                             :options="['New Launch', 'Under Construction', 'Ready to Move', 'Nearing Completion']"
                             placeholder="Any stage" />
            <x-filter-select name="status" :options="['draft' => 'Draft', 'active' => 'Active', 'archived' => 'Archived']" placeholder="Any status" />
            <x-filter-select name="developer_status"
                             :options="['pending' => 'Awaiting developer', 'accepted' => 'Accepted', 'declined' => 'Declined']"
                             placeholder="Any developer response" />
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
            <x-th>Dev. response</x-th>
            <x-th align="right">Actions</x-th>
        </x-slot:head>

        @foreach($properties as $p)
            {{-- Whole row opens the project, except where the row's own menu was clicked —
                 that guard is what keeps the dropdown usable. --}}
            <tr class="hover:bg-canvas transition-colors cursor-pointer"
                x-on:click="if (! $event.target.closest('[data-row-actions]')) window.location = @js(route('admin.properties.show', $p))">
                <td class="px-4 py-3">
                    <a href="{{ route('admin.properties.show', $p) }}"
                       class="text-[13px] font-medium text-ink hover:text-primary transition-colors truncate block">
                        {{ $p->name }}
                    </a>
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
                    <p class="text-[11px] text-ink-3 mt-1 truncate">{{ $p->project_status }}</p>
                </td>

                <td class="px-4 py-3 text-[12.5px] text-ink-2 nums whitespace-nowrap hidden md:table-cell">
                    {{-- price_max is no longer collected, so a band only renders when one is
                         actually on record; otherwise this reads as the entry price it is. --}}
                    @if($p->price_min === null)
                        —
                    @elseif($p->price_max === null)
                        From {{ $p->currency }} {{ number_format($p->price_min / 1_000_000, 2) }}M
                    @else
                        {{ $p->currency }} {{ number_format($p->price_min / 1_000_000, 2) }}M – {{ number_format($p->price_max / 1_000_000, 2) }}M
                    @endif
                </td>

                <td class="px-4 py-3 text-right text-[12.5px] text-ink-2 nums hidden xl:table-cell">{{ number_format($p->views_count) }}</td>
                <td class="px-4 py-3 text-right text-[12.5px] font-medium text-ink nums hidden lg:table-cell">{{ $p->interests_count }}</td>

                <td class="px-4 py-3">
                    <x-badge :tone="$statusTone[$p->listing_status] ?? 'neutral'" size="sm" dot>
                        {{ ucfirst($p->listing_status) }}
                    </x-badge>
                </td>

                {{-- The developer's half of the gate. "Live" underneath is the only
                     signal that means a channel partner can actually see this row. --}}
                <td class="px-4 py-3">
                    <x-badge :tone="$devTone[$p->developer_status] ?? 'neutral'" size="sm" dot>
                        {{ $devLabel[$p->developer_status] ?? ucfirst($p->developer_status) }}
                    </x-badge>
                    @if($p->listing_status === 'active' && $p->developer_status === 'accepted')
                        <p class="text-[10.5px] text-success mt-1">Live to partners</p>
                    @elseif($p->developer_status === 'pending')
                        <p class="text-[10.5px] text-ink-3 mt-1">Hidden from partners</p>
                    @endif
                </td>

                <td class="px-4 py-3 text-right" data-row-actions>
                    <x-dropdown>
                        <x-slot:trigger>
                            <button type="button" aria-label="Actions for {{ $p->name }}"
                                    class="text-ink-3 hover:text-ink hover:bg-canvas rounded-md p-1.5 transition-colors">
                                <x-icon name="dots" class="w-4 h-4" />
                            </button>
                        </x-slot:trigger>

                        <x-dropdown-item icon="eye" tag="a" href="{{ route('admin.properties.show', $p) }}">
                            View details
                        </x-dropdown-item>
                        <x-dropdown-item icon="cog" tag="a" href="{{ route('admin.properties.edit', $p) }}">
                            Edit project
                        </x-dropdown-item>
                        <x-dropdown-item icon="users" tag="a" href="{{ route('admin.leads', ['search' => $p->name]) }}">
                            View leads
                        </x-dropdown-item>

                        <div class="my-1 border-t border-line-soft"></div>

                        @foreach(['draft' => 'Move to draft', 'active' => 'Publish listing', 'archived' => 'Archive listing'] as $value => $label)
                            @if($p->listing_status !== $value)
                                <form method="POST" action="{{ route('admin.properties.update', $p) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="listing_status" value="{{ $value }}">
                                    <x-dropdown-item :icon="$value === 'active' ? 'check' : ($value === 'archived' ? 'x' : 'list')"
                                                     tag="button" type="submit">{{ $label }}</x-dropdown-item>
                                </form>
                            @endif
                        @endforeach

                        <div class="my-1 border-t border-line-soft"></div>

                        {{-- Soft delete: leads cascade on a hard delete, so the row is kept. --}}
                        <form method="POST" action="{{ route('admin.properties.destroy', $p) }}"
                              x-on:submit.prevent="$dispatch('confirm-request', {
                                  title: 'Delete this project?',
                                  message: @js('"' . $p->name . '" will be removed from every listing and from channel partner view. Leads already raised against it are kept.'),
                                  confirmLabel: 'Delete project',
                                  tone: 'danger',
                                  form: $el,
                              }); close()">
                            @csrf @method('DELETE')
                            <x-dropdown-item icon="x" tone="danger" tag="button" type="submit">
                                Delete project
                            </x-dropdown-item>
                        </form>
                    </x-dropdown>
                </td>
            </tr>
        @endforeach
    </x-data-table>
</x-layouts.admin>
