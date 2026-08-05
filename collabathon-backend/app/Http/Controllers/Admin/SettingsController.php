<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\BrokerApprovedMail;
use App\Models\Amenity;
use App\Models\City;
use App\Models\Country;
use App\Models\FormField;
use App\Models\MeasurementUnit;
use App\Models\ProjectType;
use App\Models\UnitType;
use App\Models\State;
use App\Models\Setting;
use App\Models\User;
use App\Support\MailSettings;
use Illuminate\Http\RedirectResponse;
use App\Services\Fcm;
use App\Support\FirebaseCredentials;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(Request $request): View
    {
        /**
         * The location cascade's position, driven by ?country= and ?state=.
         *
         * Selection is resolved against the data rather than trusted from the query: a
         * stale `?state=` left over after a delete would otherwise render a city list
         * belonging to nothing. Falling back to the first row keeps the panel usable
         * instead of showing three empty columns.
         */
        $countries = Country::withCount('states')->orderBy('name')->get();

        $selectedCountry = $countries->firstWhere('id', (int) $request->query('country'))
            ?? $countries->first();

        $states = $selectedCountry
            ? State::where('country_id', $selectedCountry->id)->withCount('cities')->orderBy('name')->get()
            : collect();

        $selectedState = $states->firstWhere('id', (int) $request->query('state'))
            ?? $states->first();

        $cities = $selectedState
            ? City::where('state_id', $selectedState->id)->orderBy('name')->get()
            : collect();

        // Project counts drive the delete guard's wording in the panel.
        $projectTypes = ProjectType::ordered()->get()
            ->each(fn ($type) => $type->projects_count = $type->projectCount());

        return view('admin.settings', [
            'countries' => $countries,
            'states' => $states,
            'cities' => $cities,
            'selectedCountry' => $selectedCountry,
            'selectedState' => $selectedState,
            'projectTypes' => $projectTypes,
            // withCount over PropertyUnitType.label — the same name-not-id link the
            // project types use, so the panel can warn before a rename or a delete.
            'unitTypes' => UnitType::ordered()->get()
                ->each(fn (UnitType $t) => $t->setAttribute('usage_count', $t->usageCount())),
            // Project counts, not row counts: an amenity lives inside one JSON array per
            // project, so the guard's wording is "listed on N projects".
            'amenities' => Amenity::ordered()->get()
                ->each(fn (Amenity $a) => $a->setAttribute('usage_count', $a->usageCount())),
            'measurementUnits' => MeasurementUnit::ordered()->get()
                ->each(fn (MeasurementUnit $u) => $u->setAttribute('usage_count', $u->usageCount())),
            'firebase' => [
                'configured' => FirebaseCredentials::isConfigured(),
                // Identify the account without exposing it — neither of these can send.
                'project_id' => FirebaseCredentials::projectId(),
                'client_email' => FirebaseCredentials::clientEmail(),
                'uploaded_at' => FirebaseCredentials::uploadedAt(),
                'path' => FirebaseCredentials::path(),
            ],
            'fieldGroups' => FormField::orderBy('sort_order')->get()->groupBy('form'),
            'accentColor' => Setting::get('accent_color', '#C9A227'),
            'mail' => [
                'configured' => MailSettings::isConfigured(),
                // The key identifies the account and is safe to show; the secret is the
                // credential and is never rendered back, only replaced.
                'api_key' => MailSettings::apiKey(),
                'masked_key' => MailSettings::maskedApiKey(),
                'has_secret' => filled(MailSettings::secretKey()),
                'from_address' => MailSettings::fromAddress(),
                'from_name' => MailSettings::fromName(),
            ],
        ]);
    }

    /**
     * Save the Mailjet credentials and the sender identity.
     *
     * A blank secret means "keep what is stored" — the field cannot be prefilled, because
     * the value is encrypted and deliberately never sent back to the browser, so requiring
     * it on every save would force the admin to re-enter it to change a from-name.
     */
    public function updateMail(Request $request): RedirectResponse
    {
        $this->authorize('edit-module', 'settings');

        $data = $request->validate([
            'mailjet_api_key' => ['required', 'string', 'max:191'],
            'mailjet_secret_key' => [MailSettings::isConfigured() ? 'nullable' : 'required', 'string', 'max:191'],
            'mail_from_address' => ['required', 'email', 'max:191'],
            'mail_from_name' => ['required', 'string', 'max:191'],
        ], [
            'mailjet_secret_key.required' => 'Enter the secret key the first time you connect Mailjet.',
            'mail_from_address.email' => 'The from address must be a valid address Mailjet has verified.',
        ]);

        Setting::put(MailSettings::KEY_API, trim($data['mailjet_api_key']));
        Setting::put(MailSettings::KEY_FROM_ADDRESS, trim($data['mail_from_address']));
        Setting::put(MailSettings::KEY_FROM_NAME, trim($data['mail_from_name']));

        if (filled($data['mailjet_secret_key'] ?? null)) {
            MailSettings::putSecret(trim($data['mailjet_secret_key']));
        }

        return back()->with('status', 'Email settings saved. Send a test to confirm they work.');
    }

    /**
     * Send the real approval email to a chosen address.
     *
     * Deliberately the same mailable an approved broker receives, not a "this is a test"
     * stub: the point is to see what they will see, and to prove the template renders as
     * well as that the credentials authenticate.
     */
    public function testMail(Request $request): RedirectResponse
    {
        $this->authorize('edit-module', 'settings');

        $data = $request->validate(['test_email' => ['required', 'email']]);

        if (! MailSettings::apply()) {
            return back()->with('warning', 'Add a Mailjet API key and secret before sending a test.');
        }

        // A stand-in broker so nothing has to exist in the database to run the test.
        $sample = new User([
            'name' => $request->user()->name,
            'email' => $data['test_email'],
        ]);

        try {
            Mail::to($data['test_email'])->send(new BrokerApprovedMail($sample, 'Example-Pass-1234'));
        } catch (\Throwable $e) {
            // The SMTP error is the whole value of a test — showing "failed" without it
            // leaves the admin guessing between a wrong key and an unverified sender.
            return back()->with('warning', 'Mailjet rejected the send: ' . $e->getMessage());
        }

        return back()->with('status', "Test email sent to {$data['test_email']}.");
    }

    /** Toggle a single form field on/off. */
    public function toggleField(Request $request, FormField $field): RedirectResponse
    {
        $this->authorize('edit-module', 'settings');

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
        $this->authorize('edit-module', 'settings');

        $data = $request->validate([
            'accent_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        Setting::put('accent_color', $data['accent_color']);

        return back()->with('status', 'Theme saved. It applies on the next app launch.');
    }

    /**
     * Replace the Firebase service account.
     *
     * Gated on manage-team, not edit-module:settings. This key can send as the entire
     * Firebase project; that is a super-admin bar, above the one for toggling a form
     * field on the same page.
     */
    public function updateFirebase(Request $request): RedirectResponse
    {
        $this->authorize('manage-team');

        $request->validate([
            // No `mimes:json` — browsers report .json inconsistently (application/json,
            // text/plain, octet-stream), so the real check is parsing it below.
            'credentials' => ['required', 'file', 'max:16'],
        ], [
            'credentials.required' => 'Choose the service account JSON to upload.',
            'credentials.max' => 'A service account key is a couple of kilobytes — that file is too large to be one.',
        ]);

        $error = FirebaseCredentials::store($request->file('credentials'));

        return $error === null
            ? back()->with('success', 'Firebase service account saved. Push notifications are live.')
            : back()->with('error', $error);
    }

    /** Removes the key. Push then no-ops and says so in the log, rather than erroring. */
    public function forgetFirebase(): RedirectResponse
    {
        $this->authorize('manage-team');

        FirebaseCredentials::forget();

        return back()->with('warning', 'Firebase service account removed — no push notifications will send.');
    }

    /**
     * Proves this server can actually reach FCM, which is the half the file alone cannot
     * tell you: outbound HTTPS to Google is blocked on plenty of hosts.
     */
    public function testFirebase(Fcm $fcm): RedirectResponse
    {
        $this->authorize('manage-team');

        if (! $fcm->configured()) {
            return back()->with('error', 'No service account is saved yet.');
        }

        // A well-formed but non-existent token: reaching FCM at all proves the OAuth2
        // exchange worked. FCM rejecting the token is the expected result.
        $result = $fcm->send(['admin-panel-probe-token'], 'Probe', 'Connectivity check');

        return $result['invalid'] === [] && $result['sent'] === 0
            ? back()->with('error', 'Could not reach Firebase. Check outbound HTTPS from this server, then see storage/logs.')
            : back()->with('success', 'Connected to Firebase — push notifications can be sent from this server.');
    }
}
