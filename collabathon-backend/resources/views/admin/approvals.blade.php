<x-layouts.admin active="approvals" title="Pending Approvals" section="Manage">

    <x-page-header
        title="Pending Approvals"
        subtitle="Channel partners cannot sign in to the mobile app until an admin approves their registration. Approving issues their access immediately.">
        <x-slot:actions>
            {{-- Drafts and Decided both live on their own pages: this one is work
                 outstanding, those are "not yet finished" and "already resolved". The
                 count badge is what actually makes Drafts noticeable — without it the
                 button looks the same whether there are 0 or 40 half-finished sign-ups
                 waiting behind it. --}}
            <x-button variant="subtle" tag="a" icon="sparkles" href="{{ route('admin.approvals.drafts') }}">
                Drafts
                @if($stats['drafts'] > 0)
                    <span class="ml-0.5 min-w-[18px] h-[18px] px-1 inline-flex items-center justify-center rounded-badge
                                 bg-primary/12 text-primary-dark text-[10.5px] font-semibold nums shrink-0">{{ $stats['drafts'] }}</span>
                @endif
            </x-button>
            <x-button variant="subtle" tag="a" icon="check" href="{{ route('admin.approvals.decided') }}">
                Decided
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3.5 mb-5">
        {{-- This table only ever shows pending rows, so there's nothing to "filter" —
             the card just clears whatever search/city filter is active, back to the
             full queue. --}}
        <a href="{{ route('admin.approvals') }}" class="block">
            <x-stat-card icon="clock" label="Awaiting review" :value="$stats['pending']"
                         class="hover:border-ink-3 transition-colors cursor-pointer" />
        </a>
        {{-- Approved/Rejected aren't rows in this table at all — they're decisions —
             so these jump to the Decided page pre-filtered, rather than pretending to
             filter a table that can't show them. `window=30d` rides along too — these
             counts are a 30-day window, and without it the Decided page would show
             every approval/rejection ever, a bigger number than the card just showed. --}}
        <a href="{{ route('admin.approvals.decided', ['outcome' => 'approved', 'window' => '30d']) }}" class="block">
            <x-stat-card icon="check" label="Approved (30d)" :value="$stats['approved']"
                         class="hover:border-ink-3 transition-colors cursor-pointer" />
        </a>
        <a href="{{ route('admin.approvals.decided', ['outcome' => 'rejected', 'window' => '30d']) }}" class="block">
            <x-stat-card icon="x" label="Rejected (30d)" :value="$stats['rejected']"
                         class="hover:border-ink-3 transition-colors cursor-pointer" />
        </a>
        {{-- Wrapped in a link — stat-card itself renders a plain div — so this tile
             doubles as the most visible way into the drafts queue, not just a count. --}}
        <a href="{{ route('admin.approvals.drafts') }}" class="block">
            <x-stat-card icon="sparkles" label="Drafts in progress"
                         :value="$stats['drafts'] === 0 ? 'None' : $stats['drafts']"
                         class="hover:border-ink-3 transition-colors cursor-pointer" />
        </a>
    </div>

    @include('admin.approvals.partials.table', ['pending' => $pending, 'cities' => $cities])
</x-layouts.admin>
