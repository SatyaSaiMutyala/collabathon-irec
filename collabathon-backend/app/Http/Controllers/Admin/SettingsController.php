<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FormField;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(): View
    {
        return view('admin.settings', [
            'fieldGroups' => FormField::orderBy('sort_order')->get()->groupBy('form'),
            'accentColor' => Setting::get('accent_color', '#C9A227'),
        ]);
    }

    /** Toggle a single form field on/off. */
    public function toggleField(Request $request, FormField $field): RedirectResponse
    {
        $data = $request->validate(['enabled' => ['required', 'boolean']]);

        // A required core field cannot be switched off — the mobile form depends on it.
        if ($field->is_core && ! $data['enabled']) {
            return back()->withErrors([
                'field' => "\"{$field->label}\" is a required core field and cannot be disabled.",
            ]);
        }

        $field->update(['enabled' => $data['enabled']]);

        return back()->with('status', "\"{$field->label}\" updated.");
    }

    public function updateTheme(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'accent_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        Setting::put('accent_color', $data['accent_color']);

        return back()->with('status', 'Theme saved. It applies on the next app launch.');
    }
}
