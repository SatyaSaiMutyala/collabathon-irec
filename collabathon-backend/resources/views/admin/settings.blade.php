@php
use App\Support\AdminMockData;
$fieldGroups = AdminMockData::configurableFields();
$themeColors = [
    ['name' => 'Gold (Default)', 'hex' => '#C9A227', 'active' => true],
    ['name' => 'Deep Navy', 'hex' => '#28345C'],
    ['name' => 'Emerald', 'hex' => '#1F9254'],
    ['name' => 'Burgundy', 'hex' => '#8C2F39'],
    ['name' => 'Teal', 'hex' => '#0F7C82'],
];
@endphp

<x-layouts.admin active="settings" title="Settings">
    <x-page-header title="Settings" subtitle="Control what appears on registration/listing forms and the app's accent color." />

    <div class="grid grid-cols-3 gap-5">
        <div class="col-span-2 space-y-5">
            @foreach($fieldGroups as $groupName => $fields)
                <x-panel :title="$groupName">
                    <div class="divide-y divide-border">
                        @foreach($fields as $field)
                            <div class="px-5 py-3.5 flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <p class="text-[13.5px] text-navy">{{ $field['label'] }}</p>
                                    @if($field['required'])
                                        <span class="text-[11px] text-text-muted">Required</span>
                                    @endif
                                </div>
                                <x-toggle :checked="$field['enabled']" />
                            </div>
                        @endforeach
                    </div>
                    <div class="px-5 py-3.5 border-t border-border">
                        <button class="text-[12.5px] text-primary-dark font-medium hover:underline flex items-center gap-1.5">
                            <x-icon name="plus" class="w-3.5 h-3.5" /> Add Field
                        </button>
                    </div>
                </x-panel>
            @endforeach
        </div>

        <div>
            <x-panel title="App Theme Color">
                <div class="px-5 py-5">
                    <p class="text-[12.5px] text-text-secondary mb-4">Sets the accent color across the Broker and Developer mobile apps.</p>
                    <div class="flex flex-wrap gap-3.5">
                        @foreach($themeColors as $color)
                            <button type="button" class="flex flex-col items-center gap-1.5 group">
                                <span class="w-9 h-9 rounded-full block {{ !empty($color['active']) ? 'ring-2 ring-offset-2 ring-navy' : '' }}"
                                    style="background-color: {{ $color['hex'] }}"></span>
                                <span class="text-[10.5px] text-text-muted group-hover:text-navy">{{ $color['name'] }}</span>
                            </button>
                        @endforeach
                    </div>
                    <x-button variant="gold" tag="button" type="button" class="w-full mt-5">
                        Save Theme
                    </x-button>
                </div>
            </x-panel>
        </div>
    </div>
</x-layouts.admin>
