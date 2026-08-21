<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PropertyUnitType;
use App\Models\UnitType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Unit type master data — the list the project intake form's unit rows pick from, edited
 * under Settings → Unit types.
 *
 * Mirrors ProjectTypeController: every action redirects back to the settings page with
 * `tab=unit-types` so a save reopens the panel the admin was working in.
 */
class UnitTypeController extends Controller
{
    // See the matching note on SettingsController::settingsResponse() — a real
    // redirect for a normal submit, JSON for the settings page's own fetch-based one.
    private function backToPanel(Request $request, string $message, string $flash = 'success'): RedirectResponse|JsonResponse
    {
        if ($request->ajax()) {
            return response()->json(['message' => $message]);
        }

        return redirect()->route('admin.settings', ['tab' => 'unit-types'])->with($flash, $message);
    }

    private function rules(?UnitType $type = null): array
    {
        return [
            'name' => [
                'required', 'string', 'max:96',
                Rule::unique('unit_types', 'name')->ignore($type?->id),
            ],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    private function messages(): array
    {
        return [
            'name.required' => 'Enter a name for the unit type.',
            'name.unique' => 'A unit type with this name already exists.',
        ];
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('edit-module', 'settings');

        $data = $request->validate($this->rules(), $this->messages());

        // New types land at the end of the list unless a position is given.
        $data['sort_order'] ??= (int) UnitType::max('sort_order') + 10;

        $type = UnitType::create($data);

        return $this->backToPanel($request, "Unit type “{$type->name}” added.");
    }

    public function update(Request $request, UnitType $unitType): RedirectResponse|JsonResponse
    {
        $this->authorize('edit-module', 'settings');

        $data = $request->validate($this->rules($unitType), $this->messages());

        $previousName = $unitType->name;

        DB::transaction(function () use ($unitType, $data, $previousName) {
            $unitType->update($data);

            // Unit rows store the type by name, so a rename has to carry them along or
            // they would point at a type that no longer exists.
            if ($previousName !== $data['name']) {
                PropertyUnitType::where('label', $previousName)->update(['label' => $data['name']]);
            }
        });

        return $this->backToPanel($request, "Unit type “{$unitType->name}” updated.");
    }

    public function destroy(Request $request, UnitType $unitType): RedirectResponse|JsonResponse
    {
        $this->authorize('edit-module', 'settings');

        // Deleting a type still configured on projects would leave those rows pointing at
        // nothing. Turning it off is the reversible move and keeps them valid.
        if ($count = $unitType->usageCount()) {
            return $this->backToPanel(
                $request,
                "“{$unitType->name}” is used on {$count} unit row" . ($count === 1 ? '' : 's')
                . ' — change those first, or turn it off instead of deleting it.',
                'error'
            );
        }

        $name = $unitType->name;
        $unitType->delete();

        return $this->backToPanel($request, "Unit type “{$name}” removed.", 'warning');
    }
}
