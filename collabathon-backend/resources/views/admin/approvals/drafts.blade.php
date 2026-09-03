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
        {{-- Clears the "today"/"stalled" filter below, back to the full in-progress list. --}}
        <a href="{{ route('admin.approvals.drafts') }}" class="block">
            <x-stat-card icon="clock" label="In progress" :value="$stats['total']"
                         :class="(! request('filter') ? 'border-primary-ring shadow-md ' : '')
                             . 'hover:border-ink-3 transition-colors cursor-pointer'" />
        </a>
        <a href="{{ request()->fullUrlWithQuery(['filter' => 'today', 'page' => null]) }}" class="block">
            <x-stat-card icon="sparkles" label="Started today" :value="$stats['today']"
                         :class="(request('filter') === 'today' ? 'border-primary-ring shadow-md ' : '')
                             . 'hover:border-ink-3 transition-colors cursor-pointer'" />
        </a>
        <a href="{{ request()->fullUrlWithQuery(['filter' => 'stalled', 'page' => null]) }}" class="block">
            <x-stat-card icon="x" label="Stalled 7d+" :value="$stats['stalled']"
                         :class="(request('filter') === 'stalled' ? 'border-primary-ring shadow-md ' : '')
                             . 'hover:border-ink-3 transition-colors cursor-pointer'" />
        </a>
    </div>

    @include('admin.approvals.partials.drafts-table', ['drafts' => $drafts])
</x-layouts.admin>
