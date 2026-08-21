<x-layouts.admin active="settings" title="Settings" section="Configure">

    <x-page-header
        title="Settings"
        subtitle="Control what appears on registration and listing forms, and how the mobile apps are branded." />

    {{-- The tab bar + every panel live in their own partial, included here and also
         rendered directly (fragment-only, no layout) by SettingsController::index()
         for the AJAX refresh every save on this page triggers — see the note on its
         root element in admin/settings/tabs.blade.php. --}}
    @include('admin.settings.tabs')
</x-layouts.admin>
