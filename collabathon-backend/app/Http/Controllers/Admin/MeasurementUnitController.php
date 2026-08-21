<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PropertyMeasurementUnit;
use App\Models\MeasurementUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Measurement unit master data — the list behind "Project extent metric" on the project
 * intake form, edited under Settings → Measurement units.
 *
 * Mirrors ProjectTypeController: every action redirects back to the settings page with
 * `tab=measurement-units` so a save reopens the panel the admin was working in.
 */
class MeasurementUnitController extends Controller
{
    // See the matching note on SettingsController::settingsResponse() — a real
    // redirect for a normal submit, JSON for the settings page's own fetch-based one.
    private function backToPanel(Request $request, string $message, string $flash = 'success'): RedirectResponse|JsonResponse
    {
        if ($request->ajax()) {
            return response()->json(['message' => $message]);
        }

        return redirect()->route('admin.settings', ['tab' => 'measurement-units'])->with($flash, $message);
    }

    private function rules(?MeasurementUnit $type = null): array
    {
        return [
            'name' => [
                'required', 'string', 'max:96',
                Rule::unique('measurement_units', 'name')->ignore($type?->id),
            ],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    private function messages(): array
    {
        return [
            'name.required' => 'Enter a name for the measurement unit.',
            'name.unique' => 'A measurement unit with this name already exists.',
        ];
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('edit-module', 'settings');

        $data = $request->validate($this->rules(), $this->messages());

        // New types land at the end of the list unless a position is given.
        $data['sort_order'] ??= (int) MeasurementUnit::max('sort_order') + 10;

        $type = MeasurementUnit::create($data);

        return $this->backToPanel($request, "Measurement unit “{$type->name}” added.");
    }

    public function update(Request $request, MeasurementUnit $measurementUnit): RedirectResponse|JsonResponse
    {
        $this->authorize('edit-module', 'settings');

        $data = $request->validate($this->rules($measurementUnit), $this->messages());

        $previousName = $measurementUnit->name;

        DB::transaction(function () use ($measurementUnit, $data, $previousName) {
            $measurementUnit->update($data);

            // Unit rows store the type by name, so a rename has to carry them along or
            // they would point at a type that no longer exists.
            if ($previousName !== $data['name']) {
                PropertyMeasurementUnit::where('label', $previousName)->update(['label' => $data['name']]);
            }
        });

        return $this->backToPanel($request, "Measurement unit “{$measurementUnit->name}” updated.");
    }

    public function destroy(Request $request, MeasurementUnit $measurementUnit): RedirectResponse|JsonResponse
    {
        $this->authorize('edit-module', 'settings');

        // Deleting a unit projects still use would leave them pointing at nothing.
        // Turning it off is the reversible move and keeps them valid.
        if ($count = $measurementUnit->usageCount()) {
            return $this->backToPanel(
                $request,
                "“{$measurementUnit->name}” is used by {$count} project" . ($count === 1 ? '' : 's')
                . ' — change those first, or turn it off instead of deleting it.',
                'error'
            );
        }

        $name = $measurementUnit->name;
        $measurementUnit->delete();

        return $this->backToPanel($request, "Measurement unit “{$name}” removed.", 'warning');
    }
}
