<x-layouts.admin active="cp" title="Channel Partners" section="Manage">

    <x-page-header
        title="Channel Partners"
        subtitle="Channel partners who are through approval and able to sign in. New registrations are reviewed on the approvals queue before they appear here.">
        <x-slot:actions>
            <x-button variant="subtle" tag="a" icon="clock" href="{{ route('admin.approvals') }}">
                Approval queue
            </x-button>

            {{-- The one way a partner arrives here without passing through the approvals
                 queue: a roster the admin has already vetted offline. --}}
            <x-modal title="Bulk upload channel partners"
                     subtitle="One row per partner. They land approved and able to sign in — KYC scans aren't part of the sheet, so add those on each partner's page afterward."
                     width="max-w-lg"
                     :open="$errors->any() && old('_form') === 'bulk-import'">
                <x-slot:trigger>
                    <x-button variant="outline" icon="upload">Bulk upload</x-button>
                </x-slot:trigger>

                <form method="POST" action="{{ route('admin.cp.bulk-import') }}"
                      enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <input type="hidden" name="_form" value="bulk-import">

                    {{-- The template download is a button, not a link inside the sentence:
                         it is the first thing to do here, and a client reading the panel
                         should not have to find it inside a paragraph. --}}
                    <div class="rounded-lg bg-canvas border border-line px-3.5 py-3">
                        <p class="text-[12.5px] text-ink-2 leading-relaxed">
                            Start from the template so the column names match exactly.
                            <span class="text-ink-3">Name, email and a 10-digit mobile are required per row;
                                everything else is optional. Categories and zones take several values in one
                                cell, separated by <span class="font-mono">|</span>.</span>
                        </p>

                        {{-- `download` is load-bearing, not decoration: the layout's click
                             handler puts the page into its loading skeleton for any link
                             without it, and a file download fires no pageshow to clear it
                             again — the panel would sit there pretending to navigate. --}}
                        <x-button variant="outline" size="sm" tag="a" icon="download" class="mt-3" download
                                  href="{{ route('admin.cp.bulk-import.template') }}">
                            Download CSV template
                        </x-button>
                    </div>

                    <x-file-field label="CSV file" name="file" accept=".csv,text/csv" required
                                  hint="Exported from Excel/Sheets as CSV — .xlsx is not read directly." />

                    <x-button variant="gold" tag="button" type="submit" icon="upload" class="w-full">
                        Upload &amp; create
                    </x-button>
                </form>
            </x-modal>

            <x-export-menu :export="$export" />
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3.5 mb-5">
        <x-stat-card icon="users" label="Active partners" :value="$stats['total']" />
        <x-stat-card icon="building" label="Registered as company" :value="$stats['companies']" />
        <x-stat-card icon="sparkles" label="Joined (30d)" :value="$stats['joined_30d']" />
        <x-stat-card icon="list" label="Cities covered" :value="$stats['cities']" />
    </div>

    <x-data-table
        :paginator="$partners"
        label="partners"
        search-placeholder="Search by name, company, email, mobile or RERA…"
        empty-icon="users"
        empty-title="No channel partners yet"
        empty-description="Approved channel partners appear here. Try clearing the filters, or review the approval queue.">

        <x-slot:filters>
            <x-filter-select name="city" :options="$cities" placeholder="All cities" />
            <x-filter-select name="state" :options="$states" placeholder="All states" />
            <x-filter-select name="segment" :options="$segments" placeholder="All categories" />
            <x-filter-select name="type"
                             :options="['company' => 'Company', 'individual' => 'Individual']"
                             placeholder="Any type" />
        </x-slot:filters>

        <x-slot:head>
            <x-th sort="name">Partner</x-th>
            <x-th hide="lg">Contact</x-th>
            <x-th hide="md">RERA</x-th>
            <x-th hide="xl">Categories</x-th>
            <x-th sort="city" hide="lg">Location</x-th>
            <x-th sort="created_at" hide="xl">Joined</x-th>
            <x-th>Status</x-th>
            <x-th align="right">Listings</x-th>
        </x-slot:head>

        @foreach($partners as $partner)
            @php $profile = $partner->brokerProfile; @endphp
            {{-- Opens the same review page the approvals queue uses — an active partner's
                 paperwork is the same record, just at a later stage. --}}
            <tr class="hover:bg-canvas transition-colors cursor-pointer"
                x-on:click="window.location = @js(route('admin.approvals.show', $partner))">
                <td class="px-4 py-3">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <x-avatar :name="$partner->name" :src="$profile?->photo_path" size="md" />
                        <div class="min-w-0">
                            <a href="{{ route('admin.approvals.show', $partner) }}"
                               class="text-[13px] font-medium text-ink hover:underline truncate block">{{ $partner->name }}</a>
                            <p class="text-[11.5px] text-ink-3 truncate">
                                {{ $profile?->company_name ?: 'Individual' }}
                            </p>
                        </div>
                    </div>
                </td>

                <td class="px-4 py-3 hidden lg:table-cell">
                    <p class="text-[12.5px] text-ink-2 nums">{{ $partner->mobile }}</p>
                    <p class="text-[11.5px] text-ink-3 truncate">{{ $partner->email }}</p>
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
                    <p class="text-[12.5px] text-ink-2 truncate">{{ $profile?->city ?: '—' }}</p>
                    <p class="text-[11.5px] text-ink-3 truncate">{{ $profile?->state ?: '' }}</p>
                </td>

                <td class="px-4 py-3 hidden xl:table-cell">
                    <span class="text-[12.5px] text-ink-3 nums">{{ $partner->created_at->format('d M Y') }}</span>
                </td>

                <td class="px-4 py-3">
                    @if($partner->status === \App\Models\User::STATUS_INACTIVE)
                        <x-badge tone="neutral" size="sm" dot>Inactive</x-badge>
                        @if($partner->deleted_at)
                            <p class="text-[10.5px] text-ink-3 mt-1">Deleted {{ $partner->deleted_at->diffForHumans() }}</p>
                        @endif
                    @else
                        <x-badge tone="success" size="sm" dot>Active</x-badge>
                    @endif
                </td>

                <td class="px-4 py-3 text-right">
                    <span class="text-[12.5px] text-ink-2 nums">{{ $partner->accepted_leads_count }}</span>
                </td>
            </tr>
        @endforeach
    </x-data-table>
</x-layouts.admin>
