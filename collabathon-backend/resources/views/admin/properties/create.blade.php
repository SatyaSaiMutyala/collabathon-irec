<x-layouts.admin active="properties" title="Add project" section="Manage">

    <x-page-header
        title="Add project"
        subtitle="Every field on the developer's project sheet, grouped into nine steps. Only the basics are required — the rest can be filled in later while the listing sits in draft.">
        <x-slot:actions>
            <x-button variant="subtle" tag="a" href="{{ route('admin.properties') }}" icon="chevron-left">
                Back to projects
            </x-button>
        </x-slot:actions>
    </x-page-header>

    @include('admin.properties._form', ['property' => null])
</x-layouts.admin>
