<x-layouts.admin active="properties" title="Add listing" section="Manage">

    <x-page-header
        title="Add listing"
        subtitle="Every field on the developer's listing sheet, grouped into nine steps. Only the basics are required — the rest can be filled in later while the listing sits in draft.">
        <x-slot:actions>
            <x-button variant="subtle" tag="a" href="{{ route('admin.properties') }}" icon="chevron-left">
                Back to listings
            </x-button>
        </x-slot:actions>
    </x-page-header>

    @include('admin.properties._form', ['property' => null])
</x-layouts.admin>
