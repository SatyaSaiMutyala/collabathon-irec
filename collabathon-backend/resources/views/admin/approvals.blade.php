<x-layouts.admin active="approvals" title="Pending Approvals" section="Manage">

    <x-page-header
        title="Pending Approvals"
        subtitle="Channel partners cannot sign in to the mobile app until an admin approves their registration. Approving issues their access immediately.">
        <x-slot:actions>
            {{-- The decision history lives on its own page: this one is work outstanding,
                 that one is a record that only grows. --}}
            <x-button variant="subtle" tag="a" icon="check" href="{{ route('admin.approvals.decided') }}">
                Decided
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3.5 mb-5">
        <x-stat-card icon="clock" label="Awaiting review" :value="$stats['pending']" />
        <x-stat-card icon="check" label="Approved (30d)" :value="$stats['approved']" />
        <x-stat-card icon="x" label="Rejected (30d)" :value="$stats['rejected']" />
        <x-stat-card icon="sparkles" label="Decision backlog"
                     :value="$stats['pending'] === 0 ? 'Clear' : $stats['pending'] . ' open'" />
    </div>

    @include('admin.approvals.partials.table', ['pending' => $pending, 'cities' => $cities])
</x-layouts.admin>
