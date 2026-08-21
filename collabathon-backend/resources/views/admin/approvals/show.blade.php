<x-layouts.admin active="approvals" :title="$broker->name" section="Manage">
    @include('admin.approvals.partials.detail', ['broker' => $broker, 'profile' => $profile])
</x-layouts.admin>
