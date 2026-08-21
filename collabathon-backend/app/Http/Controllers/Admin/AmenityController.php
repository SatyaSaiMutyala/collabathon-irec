<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Amenity;
use App\Models\PropertyDetail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Amenity master data — the checkbox list on the project intake form, edited under
 * Settings → Amenities.
 *
 * Mirrors UnitTypeController: every action redirects back to the settings page with
 * `tab=amenities` so a save reopens the panel the admin was working in.
 */
class AmenityController extends Controller
{
    // See the matching note on SettingsController::settingsResponse() — a real
    // redirect for a normal submit, JSON for the settings page's own fetch-based one.
    private function backToPanel(Request $request, string $message, string $flash = 'success'): RedirectResponse|JsonResponse
    {
        if ($request->ajax()) {
            return response()->json(['message' => $message]);
        }

        return redirect()->route('admin.settings', ['tab' => 'amenities'])->with($flash, $message);
    }

    private function rules(?Amenity $amenity = null): array
    {
        return [
            'name' => [
                'required', 'string', 'max:96',
                Rule::unique('amenities', 'name')->ignore($amenity?->id),
            ],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    private function messages(): array
    {
        return [
            'name.required' => 'Enter a name for the amenity.',
            'name.unique' => 'An amenity with this name already exists.',
        ];
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('edit-module', 'settings');

        $data = $request->validate($this->rules(), $this->messages());

        // New amenities land at the end of the grid unless a position is given.
        $data['sort_order'] ??= (int) Amenity::max('sort_order') + 10;

        $amenity = Amenity::create($data);

        return $this->backToPanel($request, "Amenity “{$amenity->name}” added.");
    }

    public function update(Request $request, Amenity $amenity): RedirectResponse|JsonResponse
    {
        $this->authorize('edit-module', 'settings');

        $data = $request->validate($this->rules($amenity), $this->messages());

        $previousName = $amenity->name;

        DB::transaction(function () use ($amenity, $data, $previousName) {
            $amenity->update($data);

            if ($previousName === $data['name']) {
                return;
            }

            // Projects store the amenity by name inside a JSON array, so a rename has to
            // rewrite each array rather than update a column. Only the rows that actually
            // contain the old name are touched, and the position within the array is kept
            // so a project's amenity order does not shuffle on an unrelated rename.
            PropertyDetail::whereJsonContains('amenities', $previousName)
                ->get()
                ->each(function (PropertyDetail $detail) use ($previousName, $data) {
                    $detail->amenities = collect($detail->amenities ?? [])
                        ->map(fn ($name) => $name === $previousName ? $data['name'] : $name)
                        ->unique()
                        ->values()
                        ->all();

                    $detail->save();
                });
        });

        return $this->backToPanel($request, "Amenity “{$amenity->name}” updated.");
    }

    public function destroy(Request $request, Amenity $amenity): RedirectResponse|JsonResponse
    {
        $this->authorize('edit-module', 'settings');

        // Deleting an amenity still listed on projects would leave those lists naming
        // something the admin can no longer see or manage. Turning it off is the
        // reversible move and keeps the projects using it intact.
        if ($count = $amenity->usageCount()) {
            return $this->backToPanel(
                $request,
                "“{$amenity->name}” is listed on {$count} project" . ($count === 1 ? '' : 's')
                . ' — remove it there first, or turn it off instead of deleting it.',
                'error'
            );
        }

        $name = $amenity->name;
        $amenity->delete();

        return $this->backToPanel($request, "Amenity “{$name}” removed.", 'warning');
    }
}
