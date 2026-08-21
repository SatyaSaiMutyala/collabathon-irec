<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\ProjectType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Project type master data — the list the project intake form and the Projects filter
 * pick from, edited under Settings → Project types.
 *
 * Mirrors LocationController: every action redirects back to the settings page with
 * `tab=project-types` so a save reopens the panel the admin was working in rather than
 * dropping them on Form fields.
 */
class ProjectTypeController extends Controller
{
    // See the matching note on SettingsController::settingsResponse() — a real
    // redirect for a normal submit, JSON for the settings page's own fetch-based one.
    private function backToPanel(Request $request, string $message, string $flash = 'success'): RedirectResponse|JsonResponse
    {
        if ($request->ajax()) {
            return response()->json(['message' => $message]);
        }

        return redirect()->route('admin.settings', ['tab' => 'project-types'])->with($flash, $message);
    }

    private function rules(?ProjectType $type = null): array
    {
        return [
            'name' => [
                'required', 'string', 'max:96',
                Rule::unique('project_types', 'name')->ignore($type?->id),
            ],
            'requires_possession_date' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    private function messages(): array
    {
        return [
            'name.required' => 'Enter a name for the project type.',
            'name.unique' => 'A project type with this name already exists.',
        ];
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('edit-module', 'settings');

        $data = $request->validate($this->rules(), $this->messages());

        // New types land at the end of the list unless a position is given.
        $data['sort_order'] ??= (int) ProjectType::max('sort_order') + 10;

        $type = ProjectType::create($data);

        return $this->backToPanel($request, "Project type “{$type->name}” added.");
    }

    public function update(Request $request, ProjectType $projectType): RedirectResponse|JsonResponse
    {
        $this->authorize('edit-module', 'settings');

        $data = $request->validate($this->rules($projectType), $this->messages());

        $previousName = $projectType->name;

        DB::transaction(function () use ($projectType, $data, $previousName) {
            $projectType->update($data);

            // Projects store the type by name, so a rename has to carry them along or
            // they would point at a type that no longer exists.
            if ($previousName !== $data['name']) {
                Property::where('project_type', $previousName)->update(['project_type' => $data['name']]);
            }
        });

        return $this->backToPanel($request, "Project type “{$projectType->name}” updated.");
    }

    public function destroy(Request $request, ProjectType $projectType): RedirectResponse|JsonResponse
    {
        $this->authorize('edit-module', 'settings');

        // Deleting a type that projects still use would leave them referencing nothing
        // and failing validation on their next edit. Pausing it is the reversible move.
        if ($count = $projectType->projectCount()) {
            return $this->backToPanel(
                $request,
                "“{$projectType->name}” is used by {$count} project" . ($count === 1 ? '' : 's')
                . ' — switch those to another type first, or turn it off instead of deleting it.',
                'error'
            );
        }

        $name = $projectType->name;
        $projectType->delete();

        return $this->backToPanel($request, "Project type “{$name}” removed.", 'warning');
    }
}
