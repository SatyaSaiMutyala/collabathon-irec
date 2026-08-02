@props(['modules', 'permissions' => null, 'name' => 'permissions'])

@php
$permissions ??= collect();
@endphp

<div class="overflow-hidden rounded-lg border border-line">
    <table class="w-full text-left border-collapse">
        <thead>
            <tr class="bg-canvas border-b border-line-soft">
                <th class="px-3.5 py-2.5 text-[11.5px] font-semibold text-ink-2">Module</th>
                <th class="px-3 py-2.5 text-[11.5px] font-semibold text-ink-2 text-center">View</th>
                <th class="px-3 py-2.5 text-[11.5px] font-semibold text-ink-2 text-center">Edit</th>
                <th class="px-3 py-2.5 text-[11.5px] font-semibold text-ink-2 text-center">Delete</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-line-soft">
            @foreach($modules as $module => $label)
                @php $existing = $permissions->firstWhere('module', $module); @endphp
                <tr>
                    <td class="px-3.5 py-2.5 text-[12.5px] text-ink">{{ $label }}</td>
                    @foreach(['view', 'edit', 'delete'] as $ability)
                        <td class="px-3 py-2.5 text-center">
                            <input type="checkbox" name="{{ $name }}[{{ $module }}][{{ $ability }}]" value="1"
                                   @checked($existing?->{"can_{$ability}"})
                                   class="w-4 h-4 border-line text-primary focus:ring-primary-ring focus:ring-2">
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
