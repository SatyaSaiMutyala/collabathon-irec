<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Developer;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Session auth for the web panel. Mobile roles use Sanctum tokens instead. */
class AuthController extends Controller
{
    public function show(): View|RedirectResponse
    {
        if (Auth::check() && Auth::user()->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        // Live figures for the brand-panel stats — same definitions the dashboard uses:
        // active channel partners, all developer accounts, every lead on record.
        return view('auth.login', [
            'stats' => [
                ['value' => User::role(User::ROLE_BROKER)->status(User::STATUS_ACTIVE)->count(), 'label' => 'Channel Partners'],
                ['value' => Developer::count(), 'label' => 'Developers'],
                ['value' => Lead::count(), 'label' => 'Leads tracked'],
            ],
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        $user = Auth::user();

        // Only admins get a panel session; anything else is signed straight back out.
        if (! $user->isAdmin() || ! $user->isActive()) {
            Auth::logout();
            $request->session()->invalidate();

            throw ValidationException::withMessages([
                'email' => 'This account cannot access the admin panel.',
            ]);
        }

        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
