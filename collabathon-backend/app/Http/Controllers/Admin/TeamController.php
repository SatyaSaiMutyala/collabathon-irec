<?php

namespace App\Http\Controllers\Admin;

use App\Http\Concerns\HandlesListQueries;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Super-Admin-only: create admin-side staff accounts and assign each a Role.
 * Mirrors DeveloperController's temp-password pattern exactly — same UX, no new
 * account-creation flow invented.
 */
class TeamController extends Controller
{
    use HandlesListQueries;

    protected function defaultPerPage(): int
    {
        return 15;
    }

    public function index(Request $request): View
    {
        $query = User::role(User::ROLE_ADMIN)->with('adminRole')->orderBy('name');
        $roles = Role::orderBy('is_system', 'desc')->orderBy('name')->get();

        return view('admin.team', [
            'members' => $this->paginate($query, $request),
            'roles' => $roles,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Trimmed + lower-cased before validation so "Sai@Gmail.com" collides with an
        // existing "sai@gmail.com" instead of slipping past the unique rule.
        $request->merge([
            'name' => trim((string) $request->input('name')),
            'email' => strtolower(trim((string) $request->input('email'))),
        ]);

        $data = $request->validate($this->rules(), $this->messages());

        // Typed in the form when the admin wants a specific one; otherwise generated.
        // Either way they change it on first sign-in.
        // `nullable` means validate() drops the key when it was not submitted at all.
        $password = ($data['password'] ?? '') ?: Str::password(14, symbols: false);

        $member = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $password,
            'role' => User::ROLE_ADMIN,
            'role_id' => $data['role_id'],
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        // Explicit redirect rather than back(): lands on a clean page 1 with no stale
        // filter/page query string, so the row just created is actually on screen.
        // The password rides in its own flash key — it belongs in the credentials
        // dialog, not spelled out in a toast that sits on screen for anyone to read.
        return redirect()
            ->route('admin.team')
            ->with('success', "{$member->name} added to the team.")
            ->with('created_id', $member->id)
            ->with('credentials', [
                'name' => $member->name,
                'email' => $member->email,
                'password' => $password,
            ]);
    }

    /**
     * Set a new password and re-open the credentials dialog with it.
     *
     * Stored passwords are hashed, so the original can never be read back — handing
     * over credentials again after the create dialog is dismissed means setting a new
     * one. The admin types it in the dialog; a blank field falls back to a generated
     * password. Any active API token is revoked so the old password stops working
     * straight away, matching what ApprovalController::reject() does.
     */
    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->isAdmin(), 404);

        $data = $request->validate([
            'password' => ['nullable', 'string', 'min:8', 'max:72'],
        ], $this->messages());

        // `nullable` means validate() drops the key when it was not submitted at all.
        $password = ($data['password'] ?? '') ?: Str::password(14, symbols: false);

        $user->update(['password' => $password]);
        $user->tokens()->delete();

        return redirect()
            ->route('admin.team')
            ->with('success', "New password issued for {$user->name}.")
            ->with('created_id', $user->id)
            ->with('credentials', [
                'name' => $user->name,
                'email' => $user->email,
                'password' => $password,
            ]);
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', 'unique:users,email'],
            // 72 is bcrypt's hard limit — anything longer is silently truncated.
            'password' => ['nullable', 'string', 'min:8', 'max:72'],
            'role_id' => ['required', Rule::exists('roles', 'id')],
        ];
    }

    protected function messages(): array
    {
        return [
            'name.required' => 'Enter the team member’s full name.',
            'name.min' => 'The name is too short — enter their full name.',
            'email.required' => 'Enter an email address — it becomes their login.',
            'email.email' => 'Enter a valid email address, e.g. name@company.ae.',
            'email.unique' => 'An account with this email already exists.',
            'password.min' => 'The password must be at least 8 characters, or leave it blank to generate one.',
            'password.max' => 'The password cannot be longer than 72 characters.',
            'role_id.required' => 'Select a role for this team member.',
            'role_id.exists' => 'Select a valid role.',
        ];
    }

    /**
     * Serves both the full Edit dialog and the quick role/status items in the row menu —
     * name and email are `sometimes`, so the quick actions can keep posting just the two
     * fields they own without wiping the rest of the record.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->isAdmin(), 404);

        if ($request->has('name')) {
            $request->merge(['name' => trim((string) $request->input('name'))]);
        }
        if ($request->has('email')) {
            $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:255'],
            'email' => [
                'sometimes', 'required', 'string', 'email:rfc', 'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'role_id' => ['required', Rule::exists('roles', 'id')],
            'status' => ['required', 'in:active,paused'],
        ], $this->messages());

        if ($blocked = $this->lastSuperAdminBlock($user, $data['role_id'], $data['status'])) {
            return back()->with('error', $blocked);
        }

        $user->update($data);

        return back()->with('success', "{$user->name} updated.");
    }

    public function destroy(User $user): RedirectResponse
    {
        abort_unless($user->isAdmin(), 404);

        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        // Passing null role/status asks the guard: "would removing this user leave
        // nobody able to administer the platform?"
        if ($blocked = $this->lastSuperAdminBlock($user, null, null)) {
            return back()->with('error', $blocked);
        }

        $name = $user->name;

        $user->tokens()->delete();
        $user->delete();

        return redirect()
            ->route('admin.team')
            ->with('warning', "{$name} removed from the team.");
    }

    /**
     * Guards the one change nobody can undo from the UI: stripping the platform of its
     * last active Super Admin, which locks everyone out of Team, Roles and every
     * permission-gated module.
     *
     * Pass the intended role/status, or null/null when the user is being deleted.
     * Returns an error message when the change must be refused, otherwise null.
     */
    private function lastSuperAdminBlock(User $user, ?int $roleId, ?string $status): ?string
    {
        if (! $user->isSuperAdmin()) {
            return null;
        }

        // Still an active Super Admin afterwards — nothing is lost.
        $staysSuperAdmin = $roleId !== null
            && $status === User::STATUS_ACTIVE
            && (bool) Role::whereKey($roleId)->value('is_system');

        if ($staysSuperAdmin) {
            return null;
        }

        $othersRemain = User::role(User::ROLE_ADMIN)
            ->status(User::STATUS_ACTIVE)
            ->whereKeyNot($user->id)
            ->whereHas('adminRole', fn ($q) => $q->where('is_system', true))
            ->exists();

        return $othersRemain
            ? null
            : 'This is the last active Super Admin — promote another member first, or you will lock everyone out.';
    }
}
