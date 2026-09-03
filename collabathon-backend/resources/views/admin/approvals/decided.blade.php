<x-layouts.admin active="approvals" title="Decided Registrations" section="Manage">

    <x-page-header
        title="Decided Registrations"
        subtitle="Every approval and rejection on record, with who decided and why. Decisions are append-only — reversing one adds a row rather than editing the old one.">
        <x-slot:actions>
            <x-button variant="subtle" tag="a" icon="clock" href="{{ route('admin.approvals') }}">
                Back to queue
            </x-button>
            <x-button variant="subtle" tag="a" icon="sparkles" href="{{ route('admin.approvals.drafts') }}">
                Drafts
            </x-button>
        </x-slot:actions>
    </x-page-header>

    {{-- "Approved"/"Rejected" are all-time counts (no window), "Decided (30d)" is the
         reverse — a window with no outcome. Each card explicitly nulls out the other
         card's key rather than only merging its own in, so clicking between them always
         shows a clean, correctly-matching view instead of one silently narrowing the
         other (the CP-page bug, see cp.blade.php's own note). --}}
    <div class="grid grid-cols-3 gap-3.5 mb-5">
        <a href="{{ request()->fullUrlWithQuery(['outcome' => 'approved', 'window' => null, 'page' => null]) }}" class="block">
            <x-stat-card icon="check" label="Approved" :value="$stats['approved']"
                         :class="(request('outcome') === 'approved' ? 'border-primary-ring shadow-md ' : '')
                             . 'hover:border-ink-3 transition-colors cursor-pointer'" />
        </a>
        <a href="{{ request()->fullUrlWithQuery(['outcome' => 'rejected', 'window' => null, 'page' => null]) }}" class="block">
            <x-stat-card icon="x" label="Rejected" :value="$stats['rejected']"
                         :class="(request('outcome') === 'rejected' ? 'border-primary-ring shadow-md ' : '')
                             . 'hover:border-ink-3 transition-colors cursor-pointer'" />
        </a>
        <a href="{{ request()->fullUrlWithQuery(['window' => '30d', 'outcome' => null, 'page' => null]) }}" class="block">
            <x-stat-card icon="clock" label="Decided (30d)" :value="$stats['last_30d']"
                         :class="(request('window') === '30d' ? 'border-primary-ring shadow-md ' : '')
                             . 'hover:border-ink-3 transition-colors cursor-pointer'" />
        </a>
    </div>

    <x-data-table
        :paginator="$decisions"
        label="decisions"
        search-placeholder="Search by channel partner, company or email…"
        empty-title="No decisions recorded"
        empty-description="Approved and rejected registrations appear here.">

        <x-slot:filters>
            <x-filter-select name="outcome"
                             :options="['approved' => 'Approved', 'rejected' => 'Rejected']"
                             placeholder="Any outcome" />
            <x-filter-select name="decided_by" :options="$reviewers" placeholder="Any reviewer" icon="users" />
        </x-slot:filters>

        <x-slot:head>
            <x-th>Channel Partner</x-th>
            <x-th hide="md">Company</x-th>
            <x-th hide="lg">Decided</x-th>
            <x-th hide="xl">Reviewer</x-th>
            <x-th hide="xl">Reason</x-th>
            <x-th align="right">Outcome</x-th>
            <x-th align="right"><span class="sr-only">Actions</span></x-th>
        </x-slot:head>

        @foreach($decisions as $decision)
            {{-- Decided rows open the same review page: an approved broker's paperwork
                 still needs to be auditable after the fact. --}}
            <tr @class(['hover:bg-canvas transition-colors', 'cursor-pointer' => $decision->broker])
                @if($decision->broker)
                    {{-- Same guard the pending queue uses: without it a click on the
                         delete button below would also navigate the row. --}}
                    x-on:click="if (! $event.target.closest('[data-row-actions]')) window.location = @js(route('admin.approvals.show', [$decision->broker, 'from' => 'decided']))"
                @endif>
                <td class="px-4 py-3">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <x-avatar :name="$decision->broker?->name ?? '—'" :src="$decision->broker?->brokerProfile?->photo_path" size="sm" />
                        <div class="min-w-0">
                            @if($decision->broker)
                                <a href="{{ route('admin.approvals.show', [$decision->broker, 'from' => 'decided']) }}"
                                   class="text-[13px] font-medium text-ink hover:underline truncate block">{{ $decision->broker->name }}</a>
                                <p class="text-[11.5px] text-ink-3 truncate">{{ $decision->broker->email }}</p>
                            @else
                                {{-- The broker row is gone but the decision survives: the
                                     audit trail is the point of this table. --}}
                                <p class="text-[13px] font-medium text-ink-3 truncate">Deleted channel partner</p>
                            @endif
                        </div>
                    </div>
                </td>
                <td class="px-4 py-3 text-[12.5px] text-ink-2 hidden md:table-cell">
                    {{ $decision->broker?->brokerProfile?->company_name ?: '—' }}
                </td>
                <td class="px-4 py-3 text-[12.5px] text-ink-3 nums hidden lg:table-cell">
                    {{ $decision->created_at->format('d M Y') }}
                </td>
                <td class="px-4 py-3 text-[12.5px] text-ink-2 hidden xl:table-cell">{{ $decision->decider?->name ?: 'System' }}</td>
                <td class="px-4 py-3 text-[12.5px] text-ink-3 max-w-[28ch] truncate hidden xl:table-cell">
                    {{ $decision->reason ?: '—' }}
                </td>
                <td class="px-4 py-3 text-right">
                    <x-badge :tone="$decision->decision === 'approved' ? 'success' : 'danger'" size="sm" dot>
                        {{ ucfirst($decision->decision) }}
                    </x-badge>
                </td>

                <td class="px-4 py-3 text-right" data-row-actions>
                    {{-- A rejected registration is reachable from nowhere else — it has
                         left the pending queue and never enters the partner roster — so
                         without this there is no way to purge one. --}}
                    @if($decision->broker)
                        @php
                            $deletePayload = \Illuminate\Support\Js::from([
                                'title' => 'Delete this channel partner?',
                                'message' => "{$decision->broker->name}'s account, documents and "
                                    . 'this decision record will be permanently deleted.',
                                'confirmLabel' => 'Delete channel partner',
                                'tone' => 'danger',
                            ]);
                        @endphp

                        <form method="POST" action="{{ route('admin.approvals.destroy', $decision->broker) }}"
                              x-on:submit.prevent="$dispatch('confirm-request', { ...{{ $deletePayload }}, form: $el })">
                            @csrf @method('DELETE')
                            <x-button variant="danger-ghost" size="sm" icon="trash" tag="button" type="submit"
                                      aria-label="Delete {{ $decision->broker->name }}" />
                        </form>
                    @endif
                </td>
            </tr>
        @endforeach
    </x-data-table>
</x-layouts.admin>
