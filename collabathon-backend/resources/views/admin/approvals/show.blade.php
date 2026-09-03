{{-- `active` follows the list this registration belongs to, not the route — an approved partner opened from the roster should not light up Pending Approvals. See ApprovalController::originList(). --}}
<x-layouts.admin :active="$origin['key']" :title="$broker->name" section="Manage">
    @include('admin.approvals.partials.detail', ['broker' => $broker, 'profile' => $profile, 'origin' => $origin])
</x-layouts.admin>
