<x-layouts.admin active="properties" :title="'Edit ' . $property->name" section="Manage">

    <x-page-header
        :title="'Edit ' . $property->name"
        subtitle="The same nine steps as intake, pre-filled with what is on record. Attachments already saved stay unless you replace or remove them.">
        <x-slot:actions>
            <x-button variant="subtle" tag="a" href="{{ route('admin.properties.show', $property) }}" icon="chevron-left">
                Back to project
            </x-button>
        </x-slot:actions>
    </x-page-header>

    @include('admin.properties._form')
</x-layouts.admin>
