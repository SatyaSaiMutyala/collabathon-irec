<x-layouts.admin active="approvals" title="Draft Registrations" section="Manage">

    <x-page-header
        title="Draft Registrations"
        subtitle="Signed up but haven't finished — saved a draft partway through, or never got past step 1. Nothing here is waiting on you; it's who might be worth a nudge.">
        <x-slot:actions>
            <x-button variant="subtle" tag="a" icon="clock" href="{{ route('admin.approvals') }}">
                Pending queue
            </x-button>
            <x-button variant="subtle" tag="a" icon="check" href="{{ route('admin.approvals.decided') }}">
                Decided
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-3 gap-3.5 mb-5">
        <x-stat-card icon="clock" label="In progress" :value="$stats['total']" />
        <x-stat-card icon="sparkles" label="Started today" :value="$stats['today']" />
        <x-stat-card icon="x" label="Stalled 7d+" :value="$stats['stalled']" />
    </div>

    @include('admin.approvals.partials.drafts-table', ['drafts' => $drafts])
</x-layouts.admin>
