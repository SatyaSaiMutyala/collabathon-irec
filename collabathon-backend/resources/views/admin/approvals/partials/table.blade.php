{{--
    The pending-registrations table — its own pagination, search and city filter
    refresh this whole block in place instead of a full page reload. `data-ajax-panel`
    marks it for app.js's generic panel mechanism (same one Team's table uses): any
    link or GET form inside this element is fetched and swapped back in, rather than
    navigating. The per-row Approve button is deliberately NOT part of that contract —
    same reasoning as Team's row actions — it stays a real POST/redirect.

    This exact file is what both ApprovalController::index()'s full-page branch and its
    ajax() fragment branch render — never two versions of the same table to keep in
    sync by hand.
--}}
<div id="approvals-table" data-ajax-panel>
    <x-data-table
        :paginator="$pending"
        label="registrations"
        search-placeholder="Search by name, company, email or RERA…"
        empty-icon="check"
        empty-title="Queue is clear"
        empty-description="Every channel partner registration has been reviewed.">

        <x-slot:filters>
            <x-filter-select name="city" :options="$cities" placeholder="All cities" />
        </x-slot:filters>

        <x-slot:head>
            <x-th>Channel Partner</x-th>
            <x-th hide="lg">Contact</x-th>
            <x-th hide="md">RERA</x-th>
            <x-th hide="xl">Categories</x-th>
            <x-th hide="lg">Submitted</x-th>
            <x-th align="right">Decision</x-th>
        </x-slot:head>

        @foreach($pending as $broker)
            @php $profile = $broker->brokerProfile; @endphp
            {{-- The row opens the full registration. Clicks on the decision cell are
                 ignored so Approve/Review still work — a <tr> cannot hold an <a>. --}}
            <tr class="hover:bg-canvas transition-colors cursor-pointer"
                x-on:click="if (! $event.target.closest('[data-row-actions]')) window.location = @js(route('admin.approvals.show', $broker))">
                <td class="px-4 py-3">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <x-avatar :name="$broker->name" :src="$broker->brokerProfile?->photo_path" size="md" />
                        <div class="min-w-0">
                            <a href="{{ route('admin.approvals.show', $broker) }}"
                               class="text-[13px] font-medium text-ink hover:underline truncate block">{{ $broker->name }}</a>
                            <p class="text-[11.5px] text-ink-3 truncate">{{ $profile?->company_name ?: $broker->email }}</p>
                        </div>
                    </div>
                </td>

                <td class="px-4 py-3 hidden lg:table-cell">
                    <p class="text-[12.5px] text-ink-2 nums">{{ $broker->mobile }}</p>
                    <p class="text-[11.5px] text-ink-3 truncate">{{ $broker->email }}</p>
                </td>

                <td class="px-4 py-3 hidden md:table-cell">
                    <span class="text-[12.5px] text-ink-2 nums">{{ $profile?->rera_number ?: '—' }}</span>
                </td>

                <td class="px-4 py-3 hidden xl:table-cell">
                    <div class="flex flex-wrap gap-1">
                        @foreach(($profile?->segments ?? []) as $segment)
                            <x-badge tone="neutral" size="sm">{{ $segment }}</x-badge>
                        @endforeach
                    </div>
                </td>

                <td class="px-4 py-3 hidden lg:table-cell">
                    <span class="text-[12.5px] text-ink-3 nums">{{ $broker->created_at->format('d M Y') }}</span>
                </td>

                {{-- data-row-actions stops the row's click-through firing in here. --}}
                <td class="px-4 py-3" data-row-actions>
                    <div class="flex items-center justify-end gap-1.5">
                        {{-- The full registration is ~34 fields plus documents — too
                             much for a drawer, so review happens on its own page. --}}
                        <x-button variant="subtle" size="sm" tag="a"
                                  href="{{ route('admin.approvals.show', $broker) }}">
                            Review
                        </x-button>

                        <form method="POST" action="{{ route('admin.approvals.approve', $broker) }}">
                            @csrf
                            <x-button variant="success-ghost" size="sm" icon="check" tag="button" type="submit"
                                      aria-label="Approve {{ $broker->name }}" />
                        </form>
                    </div>
                </td>
            </tr>
        @endforeach
    </x-data-table>
</div>
