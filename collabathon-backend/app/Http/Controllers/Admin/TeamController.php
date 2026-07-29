<?php

namespace App\Http\Controllers\Admin;

use App\Http\Concerns\HandlesListQueries;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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

        return view('admin.team', [
            'members' => $this->paginate($query, $request),
            'roles' => Role::orderBy('is_system', 'desc')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role_id' => ['required', 'exists:roles,id'],
        ]);

        // Temporary credential handed to the new team member; they change it on first sign-in.
        $tempPassword = Str::password(12, symbols: false);

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $tempPassword,
            'role' => User::ROLE_ADMIN,
            'role_id' => $data['role_id'],
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        return back()->with('status', "Team member created. Temporary password: {$tempPassword}");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->isAdmin(), 404);
        abort_if($user->isSuperAdmin(), 403, 'The Super Admin account cannot be modified here.');

        $data = $request->validate([
            'role_id' => ['required', 'exists:roles,id'],
            'status' => ['required', 'in:active,paused'],
        ]);

        $user->update($data);

        return back()->with('status', "{$user->name} updated.");
    }
}
