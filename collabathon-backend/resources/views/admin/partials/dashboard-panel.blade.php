{{--
    The KPI tile drill-down — included from admin/dashboard.blade.php on first load,
    and re-rendered standalone by DashboardController::fragment() for every AJAX open,
    search, filter, sort or page change (see the interception in resources/js/app.js).
    Self-contained on purpose: it must render identically either way, so it does not
    reach for anything the parent dashboard view defines (e.g. its own $activityTones
    copy below, rather than reusing the parent's).
--}}
@if($panel)
    @php
        // Same six hues the KPI tiles use (see stat-card.blade.php) — carried onto
        // the panel as a small dot so it visibly reads as "that tile, opened" rather
        // than a disconnected block that happens to appear on click.
        $panelDots = [
            'primary' => 'bg-primary', 'info' => 'bg-info', 'success' => 'bg-success',
            'warning' => 'bg-warning', 'danger' => 'bg-danger', 'chart-2' => 'bg-[var(--color-chart-2)]',
        ];
        $panelDot = $panelDots[$panel['color'] ?? null] ?? 'bg-ink-3';

        $activityTones = [
            'info'    => 'bg-info-soft text-info',
            'success' => 'bg-success-soft text-success',
            'danger'  => 'bg-danger-soft text-danger',
            'neutral' => 'bg-canvas text-ink-3',
        ];
    @endphp
    <div id="panel" class="mb-5 scroll-mt-4 panel-reveal">
        <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 mb-3">
            <div class="min-w-0 flex items-start gap-2.5">
                <span class="w-2 h-2 rounded-full {{ $panelDot }} shrink-0 mt-[7px]"></span>
                <div class="min-w-0">
                    <h2 class="text-[15px] font-semibold text-ink tracking-[-0.01em]">{{ $panel['title'] }}</h2>
                    <p class="text-[12px] text-ink-3 mt-0.5">{{ $panel['subtitle'] }}</p>
                </div>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                {{-- Leaves the dashboard for real — not part of the AJAX interception,
                     since app.js only intercepts links back to /admin/dashboard*. A
                     proper outline button reads as "secondary action" at a glance;
                     the plain coloured text link it replaced sat on `primary-dark`,
                     which is a warm brown-orange that read as red/alarming here. --}}
                <x-button variant="outline" size="sm" tag="a" href="{{ $panel['fullRoute'] }}" icon-right="arrow-right">
                    Open full page
                </x-button>
                <a href="{{ route('admin.dashboard') }}"
                   class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-ink-3
                          hover:text-ink hover:bg-canvas transition-colors duration-200" title="Close">
                    <x-icon name="x" class="w-4 h-4" />
                </a>
            </div>
        </div>

        <x-data-table
            :paginator="$panel['rows']"
            :label="$panel['label']"
            :search-placeholder="$panel['searchPlaceholder']"
            empty-title="Nothing here"
            empty-description="Adjust the search to see more.">

            @if($panel['key'] === 'developers')
                <x-slot:filters>
                    <x-filter-select name="status" :options="['active' => 'Active', 'paused' => 'Paused']" placeholder="Any status" />
                </x-slot:filters>
            @elseif($panel['key'] === 'properties')
                <x-slot:filters>
                    <x-filter-select name="status" :options="['draft' => 'Draft', 'active' => 'Active', 'archived' => 'Archived']" placeholder="Any status" />
                </x-slot:filters>
            @endif

            <x-slot:head>
                @switch($panel['key'])
                    @case('developers')
                        <x-th>Company</x-th>
                        <x-th hide="md">Contact</x-th>
                        <x-th hide="lg">City</x-th>
                        <x-th>Status</x-th>
                        @break
                    @case('brokers')
                    @case('pending')
                        <x-th>Name</x-th>
                        <x-th hide="md">Company</x-th>
                        <x-th hide="lg">City</x-th>
                        <x-th align="right">{{ $panel['key'] === 'pending' ? 'Submitted' : 'Mobile' }}</x-th>
                        @break
                    @case('properties')
                        <x-th>Project</x-th>
                        <x-th hide="md">Developer</x-th>
                        <x-th>Status</x-th>
                        @break
                    @case('activity')
                        <x-th>Action</x-th>
                        <x-th hide="md">Subject</x-th>
                        <x-th align="right">When</x-th>
                        @break
                @endswitch
            </x-slot:head>

            @switch($panel['key'])
                @case('developers')
                    @foreach($panel['rows'] as $dev)
                        <tr class="hover:bg-canvas transition-colors cursor-pointer"
                            x-on:click="window.location = @js(route('admin.developers.show', $dev))">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <x-avatar :name="$dev->company_name" :src="$dev->logo_path" size="sm" />
                                    <a href="{{ route('admin.developers.show', $dev) }}"
                                       class="text-[13px] font-medium text-ink hover:underline truncate">{{ $dev->company_name }}</a>
                                </div>
                            </td>
                            <td class="px-4 py-3 hidden md:table-cell text-[12.5px] text-ink-2 truncate">{{ $dev->contact_person ?: '—' }}</td>
                            <td class="px-4 py-3 hidden lg:table-cell text-[12.5px] text-ink-2">{{ $dev->city ?: '—' }}</td>
                            <td class="px-4 py-3">
                                <x-badge :tone="$dev->status === 'active' ? 'success' : 'neutral'" size="sm" dot>
                                    {{ ucfirst($dev->status) }}
                                </x-badge>
                            </td>
                        </tr>
                    @endforeach
                    @break

                @case('brokers')
                @case('pending')
                    @foreach($panel['rows'] as $user)
                        @php $profile = $user->brokerProfile; @endphp
                        <tr class="hover:bg-canvas transition-colors cursor-pointer"
                            x-on:click="window.location = @js(route('admin.approvals.show', $user))">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <x-avatar :name="$user->name" :src="$profile?->photo_path" size="sm" />
                                    <a href="{{ route('admin.approvals.show', $user) }}"
                                       class="text-[13px] font-medium text-ink hover:underline truncate">{{ $user->name }}</a>
                                </div>
                            </td>
                            <td class="px-4 py-3 hidden md:table-cell text-[12.5px] text-ink-2 truncate">{{ $profile?->company_name ?: 'Individual' }}</td>
                            <td class="px-4 py-3 hidden lg:table-cell text-[12.5px] text-ink-2">{{ $profile?->city ?: '—' }}</td>
                            <td class="px-4 py-3 text-right text-[12.5px] {{ $panel['key'] === 'pending' ? 'text-ink-3' : 'text-ink-2 nums' }}">
                                {{ $panel['key'] === 'pending' ? $user->created_at->format('d M') : $user->mobile }}
                            </td>
                        </tr>
                    @endforeach
                    @break

                @case('properties')
                    @php $listingTone = ['active' => 'success', 'draft' => 'warning', 'archived' => 'neutral']; @endphp
                    @foreach($panel['rows'] as $p)
                        <tr class="hover:bg-canvas transition-colors cursor-pointer"
                            x-on:click="window.location = @js(route('admin.properties.show', $p))">
                            <td class="px-4 py-3">
                                <a href="{{ route('admin.properties.show', $p) }}"
                                   class="text-[13px] font-medium text-ink hover:underline truncate block">{{ $p->name }}</a>
                            </td>
                            <td class="px-4 py-3 hidden md:table-cell text-[12.5px] text-ink-2 truncate">{{ $p->developer?->company_name }}</td>
                            <td class="px-4 py-3">
                                <x-badge :tone="$listingTone[$p->listing_status] ?? 'neutral'" size="sm" dot>
                                    {{ ucfirst($p->listing_status) }}
                                </x-badge>
                            </td>
                        </tr>
                    @endforeach
                    @break

                @case('activity')
                    @php
                        $panelLinkFor = fn ($row) => match ($row->source) {
                            'lead' => route('admin.leads.show', $row->ref_id),
                            'approval' => route('admin.approvals.show', $row->ref_id),
                            'property' => route('admin.properties.show', $row->ref_id),
                            default => null,
                        };
                    @endphp
                    @foreach($panel['rows'] as $row)
                        @php
                            $meta = \App\Http\Controllers\Admin\ActivityController::TYPES[$row->type]
                                ?? ['label' => ucfirst($row->type), 'icon' => 'chart', 'tone' => 'neutral'];
                            $rowHref = $panelLinkFor($row);
                        @endphp
                        <tr class="hover:bg-canvas transition-colors {{ $rowHref ? 'cursor-pointer' : '' }}"
                            @if($rowHref) x-on:click="window.location = @js($rowHref)" @endif>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <span class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 {{ $activityTones[$meta['tone']] ?? 'bg-canvas text-ink-3' }}">
                                        <x-icon :name="$meta['icon']" class="w-3.5 h-3.5" />
                                    </span>
                                    <span class="text-[12.5px] text-ink truncate">
                                        {{ $meta['label'] }}@if($row->actor_name) — {{ $row->actor_name }} @endif
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3 hidden md:table-cell text-[12.5px] text-ink-2 truncate">{{ $row->subject_name ?? '—' }}</td>
                            <td class="px-4 py-3 text-right text-[11.5px] text-ink-3 whitespace-nowrap">
                                {{ \Illuminate\Support\Carbon::parse($row->occurred_at)->diffForHumans() }}
                            </td>
                        </tr>
                    @endforeach
                    @break
            @endswitch
        </x-data-table>
    </div>
@endif
