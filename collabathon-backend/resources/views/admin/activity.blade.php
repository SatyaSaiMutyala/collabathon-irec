@php
    use App\Http\Controllers\Admin\ActivityController;

    /** Where a row's own record lives — reuses the existing detail pages rather
        than inventing a new one, since the action IS that record's own history. */
    $linkFor = function ($row) {
        return match ($row->source) {
            'lead' => route('admin.leads.show', $row->ref_id),
            'approval' => route('admin.approvals.show', $row->ref_id),
            'property' => route('admin.properties.show', $row->ref_id),
            default => null,
        };
    };

    /** The sentence a row reads as — actor and subject are already the right people
        for the type (e.g. the broker for "interested", the developer for "accepted"). */
    $describe = function ($row) {
        return match ($row->type) {
            'lead_interested' => ($row->actor_name ?? 'A broker') . ' marked interest in ' . ($row->subject_name ?? 'a project'),
            'lead_accepted' => ($row->actor_name ?? 'A developer') . ' accepted a request for ' . ($row->subject_name ?? 'a project'),
            'lead_declined' => ($row->actor_name ?? 'A developer') . ' declined a request for ' . ($row->subject_name ?? 'a project'),
            'broker_approved' => ($row->actor_name ?? 'An admin') . ' approved ' . ($row->subject_name ?? 'a broker'),
            'broker_rejected' => ($row->actor_name ?? 'An admin') . ' rejected ' . ($row->subject_name ?? 'a broker'),
            'property_accepted' => ($row->actor_name ?? 'A developer') . ' accepted the project ' . ($row->subject_name ?? ''),
            'property_declined' => ($row->actor_name ?? 'A developer') . ' declined the project ' . ($row->subject_name ?? ''),
            'broker_registered' => ($row->actor_name ?? 'Someone') . ' submitted a broker registration',
            default => 'Unknown action',
        };
    };
@endphp

<x-layouts.admin active="dashboard" title="Activity" section="Overview">

    <a href="{{ route('admin.dashboard') }}"
       class="inline-flex items-center gap-1.5 text-[12.5px] text-ink-2 hover:text-ink transition-colors mb-4">
        <x-icon name="chevron-left" class="w-4 h-4" />
        Back to Dashboard
    </a>

    <x-page-header
        title="Activity"
        subtitle="Every decision on the platform — interest, acceptances, declines and registrations — click any row for the full record." />

    @php
        $thisWeek = (int) ($trend[count($trend) - 1]['count'] ?? 0);
        $lastWeek = (int) ($trend[count($trend) - 2]['count'] ?? 0);
        $weekDelta = $lastWeek > 0 ? ($thisWeek - $lastWeek) / $lastWeek * 100 : null;
    @endphp

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 mb-5">
        <x-panel title="Actions per week" subtitle="Last 12 weeks, across every action type"
                 padded class="xl:col-span-2">
            {{-- Single series: no legend box needed, the title already says what's
                 plotted. Kept short — this is a sparse weekly count, not a dense
                 metric that needs 250px to read. --}}
            <x-chart-trend
                :points="$trend"
                :series="[['key' => 'count', 'label' => 'Actions', 'color' => 'var(--color-chart-1)']]"
                :height="150" />
        </x-panel>

        <x-panel title="This week" subtitle="vs the week before" padded>
            <p class="text-[32px] font-semibold text-ink tracking-[-0.02em] leading-none">
                {{ number_format($thisWeek) }}
            </p>
            @if($weekDelta !== null)
                <p class="flex items-center gap-1 text-[11.5px] mt-1.5 {{ $weekDelta >= 0 ? 'text-success' : 'text-danger' }}">
                    <x-icon :name="$weekDelta >= 0 ? 'arrow-up' : 'arrow-down'" class="w-3 h-3 shrink-0" />
                    <span class="font-medium nums">{{ number_format(abs($weekDelta), 1) }}%</span>
                    <span class="text-ink-3 font-normal">vs last week</span>
                </p>
            @else
                <p class="text-[11.5px] text-ink-3 mt-1.5">No activity the week before to compare.</p>
            @endif
            <p class="text-[11.5px] text-ink-3 mt-4 pt-4 border-t border-line-soft leading-relaxed">
                {{ number_format($lastWeek) }} action{{ $lastWeek === 1 ? '' : 's' }} the week before.
            </p>
        </x-panel>
    </div>

    <x-data-table
        :paginator="$activities"
        label="actions"
        search-placeholder="Search by person or project…"
        empty-icon="chart"
        empty-title="No activity matches"
        empty-description="Adjust the search or clear the type filter.">

        <x-slot:filters>
            <x-filter-select name="type" :options="collect($types)->map(fn ($t) => $t['label'])" placeholder="All action types" />
        </x-slot:filters>

        <x-slot:head>
            <x-th>Action</x-th>
            <x-th hide="md">Who</x-th>
            <x-th sort="occurred_at" hide="lg">When</x-th>
            <x-th align="right"><span class="sr-only">Open</span></x-th>
        </x-slot:head>

        @foreach($activities as $row)
            @php
                $meta = ActivityController::TYPES[$row->type] ?? ['label' => ucfirst($row->type), 'icon' => 'chart', 'tone' => 'neutral'];
                $toneClasses = [
                    'success' => 'bg-success-soft text-success',
                    'danger' => 'bg-danger-soft text-danger',
                    'info' => 'bg-info-soft text-info',
                    'neutral' => 'bg-canvas text-ink-3',
                ];
                $link = $linkFor($row);
            @endphp
            <tr class="hover:bg-canvas transition-colors {{ $link ? 'cursor-pointer' : '' }}"
                @if($link) x-on:click="window.location = @js($link)" @endif>
                <td class="px-4 py-3">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <span class="w-8 h-8 shrink-0 flex items-center justify-center {{ $toneClasses[$meta['tone']] ?? $toneClasses['neutral'] }}">
                            <x-icon :name="$meta['icon']" class="w-4 h-4" />
                        </span>
                        <div class="min-w-0">
                            <p class="text-[12.5px] font-medium text-ink truncate">{{ $meta['label'] }}</p>
                            <p class="text-[11.5px] text-ink-3 truncate">{{ $describe($row) }}</p>
                        </div>
                    </div>
                </td>

                <td class="px-4 py-3 text-[12.5px] text-ink-2 truncate hidden md:table-cell">
                    {{ $row->actor_name ?? '—' }}
                </td>

                <td class="px-4 py-3 text-[12.5px] text-ink-3 nums whitespace-nowrap hidden lg:table-cell">
                    {{ \Illuminate\Support\Carbon::parse($row->occurred_at)->format('d M Y, h:i A') }}
                </td>

                <td class="px-4 py-3 text-right">
                    @if($link)
                        <x-icon name="chevron-right" class="w-4 h-4 text-ink-3 inline-block" />
                    @endif
                </td>
            </tr>
        @endforeach
    </x-data-table>
</x-layouts.admin>
