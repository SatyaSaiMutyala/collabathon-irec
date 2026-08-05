@php
    $requests = (int) $stats->requests;
    $accepted = (int) $stats->accepted;
    $pending = (int) $stats->pending;
    $declined = (int) $stats->declined;
    $acceptRate = $requests > 0 ? $accepted / $requests * 100 : 0;
@endphp

<x-layouts.admin active="leads" :title="$property->name" section="Manage">

    <a href="{{ route('admin.leads.developer', $developer) }}"
       class="inline-flex items-center gap-1.5 text-[12.5px] text-ink-2 hover:text-ink transition-colors mb-4">
        <x-icon name="chevron-left" class="w-4 h-4" />
        Back to {{ $developer->company_name }}
    </a>

    <div class="flex items-center gap-3.5 mb-6">
        @if($property->cover_image_path || $property->logo_path)
            <img src="{{ asset('storage/' . ($property->cover_image_path ?: $property->logo_path)) }}"
                 alt="" class="w-12 h-12 rounded-xl object-cover border border-line-soft shrink-0">
        @else
            <x-avatar :name="$property->name" size="lg" class="w-12 h-12 shrink-0" />
        @endif
        <div class="min-w-0">
            <h1 class="text-[19px] sm:text-[21px] font-semibold text-ink tracking-[-0.02em] leading-tight truncate">
                {{ $property->name }}
            </h1>
            <p class="text-[12.5px] text-ink-3 mt-0.5">
                {{ $developer->company_name }} · every channel partner who has requested this project.
            </p>
        </div>
        <a href="{{ route('admin.properties.show', $property) }}"
           class="ml-auto shrink-0 text-[12px] font-medium text-primary-dark hover:underline whitespace-nowrap">
            View project listing
        </a>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3.5 mb-5">
        <x-stat-card icon="sparkles" label="Requests received" :value="$requests" />
        <x-stat-card icon="check" label="Accepted" :value="$accepted" />
        <x-stat-card icon="clock" label="Pending" :value="$pending" />
        <x-stat-card icon="x" label="Declined" :value="$declined" />
    </div>

    <div x-data="{ open: false }" class="mb-5">
        <button type="button" @click="open = !open"
                class="w-full flex items-center justify-between gap-3 rounded-xl border border-line bg-panel px-4 py-3
                       shadow-card hover:border-primary-ring transition-colors">
            <span class="flex items-center gap-2.5 min-w-0">
                <span class="w-8 h-8 rounded-lg bg-primary-soft flex items-center justify-center shrink-0">
                    <x-icon name="chart" class="w-4 h-4 text-primary-dark" />
                </span>
                <span class="text-left min-w-0">
                    <span class="block text-[13px] font-medium text-ink">Analytics</span>
                    <span class="block text-[11.5px] text-ink-3 truncate">Trend and acceptance rate for this project</span>
                </span>
            </span>
            <x-icon name="chevron-down" class="w-4 h-4 text-ink-3 shrink-0 transition-transform duration-200"
                    x-bind:class="{'rotate-180': open}" />
        </button>

        <div x-show="open" x-cloak x-transition.opacity.duration.200ms class="grid grid-cols-1 xl:grid-cols-3 gap-4 mt-4">
            <x-panel title="Requests over time" subtitle="Last 12 weeks — requested, accepted, declined"
                     padded class="xl:col-span-2">
                <x-chart-trend
                    :points="$trend"
                    :series="[
                        ['key' => 'requests', 'label' => 'Requested', 'color' => 'var(--color-chart-1)'],
                        ['key' => 'accepted', 'label' => 'Accepted', 'color' => 'var(--color-success)'],
                        ['key' => 'declined', 'label' => 'Declined', 'color' => 'var(--color-danger)'],
                    ]" />
            </x-panel>

            <x-panel title="Acceptance rate" subtitle="Requested → accepted, this project" padded>
                <div class="pt-1">
                    <p class="text-[32px] font-semibold text-ink tracking-[-0.02em] leading-none">
                        {{ number_format($acceptRate, 1) }}%
                    </p>
                    <p class="text-[11.5px] text-ink-3 mt-1.5">
                        {{ number_format($accepted) }} of {{ number_format($requests) }} requests accepted
                    </p>
                </div>
                <div class="mt-5 pt-4 border-t border-line-soft space-y-2.5">
                    @php
                        $split = [
                            ['label' => 'Accepted', 'value' => $accepted, 'class' => 'bg-success'],
                            ['label' => 'Pending', 'value' => $pending, 'class' => 'bg-warning'],
                            ['label' => 'Declined', 'value' => $declined, 'class' => 'bg-danger'],
                        ];
                    @endphp
                    <div class="flex h-2.5 gap-x-0.5 bg-canvas">
                        @foreach($split as $s)
                            @if($requests > 0 && $s['value'] > 0)
                                <div class="{{ $s['class'] }}" style="width: {{ $s['value'] / $requests * 100 }}%"></div>
                            @endif
                        @endforeach
                    </div>
                    @foreach($split as $s)
                        <div class="flex items-center justify-between text-[12px]">
                            <span class="flex items-center gap-1.5 text-ink-2">
                                <span class="w-2 h-2 rounded-full {{ $s['class'] }}"></span>
                                {{ $s['label'] }}
                            </span>
                            <span class="text-ink-3 nums">{{ number_format($s['value']) }}</span>
                        </div>
                    @endforeach
                </div>
            </x-panel>
        </div>
    </div>

    {{-- ============================== Requests ============================== --}}
    <x-data-table
        :paginator="$leads"
        label="requests"
        search-placeholder="Search by channel partner…"
        empty-icon="users"
        empty-title="No activity yet"
        empty-description="Nobody has viewed or requested this project yet.">

        <x-slot:filters>
            <x-filter-select name="status"
                             :options="['viewed' => 'Viewed', 'interested' => 'Pending', 'accepted' => 'Accepted', 'declined' => 'Declined']"
                             placeholder="Any status" />
        </x-slot:filters>

        <x-slot:head>
            <x-th>Channel Partner</x-th>
            <x-th sort="created_at" hide="md">Date</x-th>
            <x-th hide="lg">Contact</x-th>
            <x-th align="right" sort="status">Status</x-th>
            <x-th align="right"><span class="sr-only">Open</span></x-th>
        </x-slot:head>

        @foreach($leads as $lead)
            <tr class="hover:bg-canvas transition-colors cursor-pointer"
                x-on:click="window.location = @js(route('admin.leads.show', $lead))">
                <td class="px-4 py-3">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <x-avatar :name="$lead->broker?->name ?? '—'" :src="$lead->broker?->brokerProfile?->photo_path" size="sm" />
                        <a href="{{ route('admin.leads.show', $lead) }}"
                           class="text-[13px] font-medium text-ink hover:text-primary transition-colors truncate">
                            {{ $lead->broker?->name ?? 'Deleted broker' }}
                        </a>
                    </div>
                </td>

                <td class="px-4 py-3 text-[12.5px] text-ink-3 nums whitespace-nowrap hidden md:table-cell">
                    {{ $lead->created_at->format('d M Y') }}
                </td>

                <td class="px-4 py-3 hidden lg:table-cell">
                    @if($lead->revealsContact())
                        <span class="inline-flex items-center gap-1.5 text-[12px] text-success">
                            <x-icon name="check" class="w-3.5 h-3.5" /> Unlocked
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 text-[12px] text-ink-3">
                            <x-icon name="lock" class="w-3.5 h-3.5" /> Locked
                        </span>
                    @endif
                </td>

                <td class="px-4 py-3 text-right">
                    <x-badge :tone="$toneMap[$lead->status] ?? 'neutral'" size="sm" dot>
                        {{ $lead->status === 'interested' ? 'Pending' : ucfirst($lead->status) }}
                    </x-badge>
                </td>

                <td class="px-4 py-3 text-right">
                    <x-icon name="chevron-right" class="w-4 h-4 text-ink-3 inline-block" />
                </td>
            </tr>
        @endforeach
    </x-data-table>
</x-layouts.admin>
