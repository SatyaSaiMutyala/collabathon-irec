<?php

namespace App\Http\Controllers\Admin;

use App\Http\Concerns\HandlesListQueries;
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
use App\Support\GoogleMapsSettings;
use App\Support\SurepassSettings;
use App\Support\WhatsAppSettings;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use App\Services\Fcm;
use App\Support\FirebaseCredentials;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    use HandlesListQueries;

    // Matches Team's own page size — see TeamController::defaultPerPage().
    protected function defaultPerPage(): int
    {
        return 10;
    }

    /**
     * Same shape as HandlesListQueries::paginate(), but for the 4 master-data tables
     * on this page: they all render at once (every tab's markup exists in the DOM
     * simultaneously, just hidden behind the inactive ones — see #settings-tabs), so
     * a shared `page` query param would have all 4 fighting over the same page
     * number. Each gets its own page name instead.
     *
     * `appends($request->query())` then overriding `tab` explicitly, rather than
     * trusting withQueryString() alone: the very first visit to this page has no
     * `?tab=` in the URL at all — switching tabs is client-side Alpine state, never
     * reflected in the address bar — so a pagination link built from that request
     * would otherwise silently drop onto the default Form fields tab on the first
     * click, before any AJAX refresh has had a chance to put `tab=` in the query
     * string itself.
     */
    private function paginateTab(Builder $query, Request $request, string $pageName, string $tab): LengthAwarePaginator
    {
        return $query->paginate($this->perPage($request), ['*'], $pageName)
            ->appends($request->query())
            ->appends(['tab' => $tab]);
    }

    public function index(Request $request): View|\Illuminate\Http\Response
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
        $projectTypes = $this->paginateTab(ProjectType::ordered(), $request, 'pt_page', 'project-types');
        $projectTypes->getCollection()->each(fn ($type) => $type->projects_count = $type->projectCount());

        $unitTypes = $this->paginateTab(UnitType::ordered(), $request, 'ut_page', 'unit-types');
        // withCount over PropertyUnitType.label — the same name-not-id link the
        // project types use, so the panel can warn before a rename or a delete.
        $unitTypes->getCollection()->each(fn (UnitType $t) => $t->setAttribute('usage_count', $t->usageCount()));

        $amenities = $this->paginateTab(Amenity::ordered(), $request, 'am_page', 'amenities');
        // Project counts, not row counts: an amenity lives inside one JSON array per
        // project, so the guard's wording is "listed on N projects".
        $amenities->getCollection()->each(fn (Amenity $a) => $a->setAttribute('usage_count', $a->usageCount()));

        $measurementUnits = $this->paginateTab(MeasurementUnit::ordered(), $request, 'mu_page', 'measurement-units');
        $measurementUnits->getCollection()->each(fn (MeasurementUnit $u) => $u->setAttribute('usage_count', $u->usageCount()));

        $data = [
            'countries' => $countries,
            'states' => $states,
            'cities' => $cities,
            'selectedCountry' => $selectedCountry,
            'selectedState' => $selectedState,
            'projectTypes' => $projectTypes,
            'unitTypes' => $unitTypes,
            'amenities' => $amenities,
            // The panel header reads "X of Y offered" — Y is $amenities->total(), but X
            // (how many of *all* of them are active) needs its own count: the paginated
            // page in hand only ever holds one page's worth of rows, active or not.
            'amenitiesActiveCount' => Amenity::where('is_active', true)->count(),
            'measurementUnits' => $measurementUnits,
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
            // Which screen a channel partner lands on first, before any account exists —
            // see ConfigController, the public endpoint the mobile app reads this from
            // pre-login.
            'cpLoginMethod' => Setting::get('cp_login_method', 'email'),
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
            'surepass' => [
                'environment' => SurepassSettings::environment(),
                'configured' => SurepassSettings::isConfigured(),
                'has_sandbox_token' => filled(SurepassSettings::sandboxToken()),
                'has_production_token' => filled(SurepassSettings::productionToken()),
                'masked_sandbox_token' => SurepassSettings::masked(SurepassSettings::sandboxToken()),
                'masked_production_token' => SurepassSettings::masked(SurepassSettings::productionToken()),
            ],
            'googleMaps' => [
                'configured' => GoogleMapsSettings::isConfigured(),
                'masked' => GoogleMapsSettings::masked(),
            ],
            'whatsapp' => [
                'environment' => WhatsAppSettings::environment(),
                'configured' => WhatsAppSettings::isConfigured(),
                'has_sandbox_token' => filled(WhatsAppSettings::sandboxToken()),
                'has_production_token' => filled(WhatsAppSettings::productionToken()),
                'masked_sandbox_token' => WhatsAppSettings::masked(WhatsAppSettings::sandboxToken()),
                'masked_production_token' => WhatsAppSettings::masked(WhatsAppSettings::productionToken()),
                'integrated_number' => WhatsAppSettings::integratedNumber(),
                'template_name' => WhatsAppSettings::templateName(),
                'template_namespace' => WhatsAppSettings::templateNamespace(),
                'template_language' => WhatsAppSettings::templateLanguage(),
            ],
        ];

        // Every mutating action on this page saves via fetch and then re-requests this
        // same URL (tab/selection preserved in the query string) to refresh in place —
        // see the note on #settings-tabs in admin/settings/tabs.blade.php. The fragment
        // is the exact same partial the full page includes, just rendered without the
        // surrounding layout, so the two can never drift out of sync with each other.
        return $request->ajax()
            ? response()->view('admin.settings.tabs', $data)
            : view('admin.settings', $data);
    }

    /**
     * Every settings action ends the same way: flash a message and redirect back for a
     * real submit (a bookmarked link, JS disabled, whatever reaches this path without
     * the page's own fetch), or hand the message back as JSON for the fetch path the
     * settings page always takes once loaded — a real redirect there would silently
     * reset the page back to its default tab, since `tab` is client-side-only state.
     */
    protected function settingsResponse(Request $request, string $message, string $flash = 'status'): RedirectResponse|JsonResponse
    {
        if ($request->ajax()) {
            return response()->json(['message' => $message]);
        }

        return back()->with($flash, $message);
    }

    /**
     * Save the Mailjet credentials and the sender identity.
     *
     * A blank secret means "keep what is stored" — the field cannot be prefilled, because
     * the value is encrypted and deliberately never sent back to the browser, so requiring
     * it on every save would force the admin to re-enter it to change a from-name.
     */
    public function updateMail(Request $request): RedirectResponse|JsonResponse
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

        return $this->settingsResponse($request, 'Email settings saved. Send a test to confirm they work.');
    }

    /**
     * Send the real approval email to a chosen address.
     *
     * Deliberately the same mailable an approved broker receives, not a "this is a test"
     * stub: the point is to see what they will see, and to prove the template renders as
     * well as that the credentials authenticate.
     */
    public function testMail(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('edit-module', 'settings');

        $data = $request->validate(['test_email' => ['required', 'email']]);

        if (! MailSettings::apply()) {
            return $this->settingsResponse($request, 'Add a Mailjet API key and secret before sending a test.', 'warning');
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
            return $this->settingsResponse($request, 'Mailjet rejected the send: ' . $e->getMessage(), 'warning');
        }

        return $this->settingsResponse($request, "Test email sent to {$data['test_email']}.");
    }

    /**
     * Save the Surepass KYC verification tokens and which environment is active.
     *
     * A blank token means "keep what is stored" — same reasoning as `updateMail()`'s
     * secret key: it is encrypted and never rendered back, so the field cannot be
     * prefilled, and requiring it on every save would force a re-paste just to flip
     * which environment is active.
     */
    public function updateSurepass(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('edit-module', 'settings');

        $data = $request->validate([
            'surepass_environment' => ['required', Rule::in([SurepassSettings::ENV_SANDBOX, SurepassSettings::ENV_PRODUCTION])],
            'surepass_sandbox_token' => [SurepassSettings::sandboxToken() ? 'nullable' : 'required_if:surepass_environment,' . SurepassSettings::ENV_SANDBOX, 'string', 'max:2048'],
            'surepass_production_token' => [SurepassSettings::productionToken() ? 'nullable' : 'required_if:surepass_environment,' . SurepassSettings::ENV_PRODUCTION, 'string', 'max:2048'],
        ], [
            'surepass_sandbox_token.required_if' => 'Enter the sandbox token before switching to it.',
            'surepass_production_token.required_if' => 'Enter the production token before switching to it.',
        ]);

        if (filled($data['surepass_sandbox_token'] ?? null)) {
            SurepassSettings::putSandboxToken(trim($data['surepass_sandbox_token']));
        }

        if (filled($data['surepass_production_token'] ?? null)) {
            SurepassSettings::putProductionToken(trim($data['surepass_production_token']));
        }

        SurepassSettings::setEnvironment($data['surepass_environment']);

        return $this->settingsResponse($request, 'KYC verification settings saved.');
    }

    /**
     * Save the MSG91 WhatsApp OTP auth keys, which environment is active, and the
     * template/number MSG91 sends through — see {@see WhatsAppSettings} and
     * {@see \App\Services\OtpSender}.
     *
     * A blank auth key means "keep what is stored", same reasoning as `updateSurepass()`.
     * The template fields are plain text (not secrets, visible on the MSG91 dashboard
     * already), so they always save from whatever the form currently holds.
     */
    public function updateWhatsApp(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('edit-module', 'settings');

        $data = $request->validate([
            'whatsapp_environment' => ['required', Rule::in([WhatsAppSettings::ENV_SANDBOX, WhatsAppSettings::ENV_PRODUCTION])],
            'whatsapp_sandbox_token' => [WhatsAppSettings::sandboxToken() ? 'nullable' : 'required_if:whatsapp_environment,' . WhatsAppSettings::ENV_SANDBOX, 'string', 'max:2048'],
            'whatsapp_production_token' => [WhatsAppSettings::productionToken() ? 'nullable' : 'required_if:whatsapp_environment,' . WhatsAppSettings::ENV_PRODUCTION, 'string', 'max:2048'],
            'whatsapp_integrated_number' => ['required', 'string', 'max:32'],
            'whatsapp_template_name' => ['required', 'string', 'max:255'],
            'whatsapp_template_namespace' => ['nullable', 'string', 'max:255'],
            'whatsapp_template_language' => ['required', 'string', 'max:16'],
        ], [
            'whatsapp_sandbox_token.required_if' => 'Enter the sandbox auth key before switching to it.',
            'whatsapp_production_token.required_if' => 'Enter the production auth key before switching to it.',
        ]);

        if (filled($data['whatsapp_sandbox_token'] ?? null)) {
            WhatsAppSettings::putSandboxToken(trim($data['whatsapp_sandbox_token']));
        }

        if (filled($data['whatsapp_production_token'] ?? null)) {
            WhatsAppSettings::putProductionToken(trim($data['whatsapp_production_token']));
        }

        WhatsAppSettings::putConfig(
            trim($data['whatsapp_integrated_number']),
            trim($data['whatsapp_template_name']),
            filled($data['whatsapp_template_namespace'] ?? null) ? trim($data['whatsapp_template_namespace']) : null,
            trim($data['whatsapp_template_language']),
        );

        WhatsAppSettings::setEnvironment($data['whatsapp_environment']);

        return $this->settingsResponse($request, 'WhatsApp OTP settings saved.');
    }

    /**
     * Save the Google Maps API key the mobile app's location-picker map screen uses —
     * read by the mobile app's public /config endpoint. Saving it here does not alone
     * make Android's map view work: the native Google Maps SDK reads its key from the
     * compiled app, so this key still needs copying into the mobile project's Android
     * build config and a rebuild before it takes effect there. iOS needs no key.
     */
    public function updateGoogleMaps(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('edit-module', 'settings');

        $data = $request->validate([
            'google_maps_api_key' => [
                GoogleMapsSettings::isConfigured() ? 'nullable' : 'required',
                'string', 'max:512',
            ],
        ]);

        if (filled($data['google_maps_api_key'] ?? null)) {
            GoogleMapsSettings::put(trim($data['google_maps_api_key']));
        }

        return $this->settingsResponse($request, 'Google Maps API key saved. Copy it into the mobile app\'s Android build config and rebuild for it to take effect there.');
    }

    /** Toggle a single form field on/off. */
    public function toggleField(Request $request, FormField $field): RedirectResponse|JsonResponse
    {
        $this->authorize('edit-module', 'settings');

        $data = $request->validate(['enabled' => ['required', 'boolean']]);

        // A required core field cannot be switched off — the mobile form depends on it.
        if ($field->is_core && ! $data['enabled']) {
            $message = "\"{$field->label}\" is a required core field and cannot be disabled.";

            if ($request->ajax()) {
                return response()->json(['message' => $message], 422);
            }

            return back()->withErrors(['field' => $message]);
        }

        $field->update(['enabled' => $data['enabled']]);

        return $this->settingsResponse($request, "\"{$field->label}\" updated.");
    }

    public function updateTheme(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('edit-module', 'settings');

        $data = $request->validate([
            'accent_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        Setting::put('accent_color', $data['accent_color']);

        return $this->settingsResponse($request, 'Theme saved. It applies on the next app launch.');
    }

    /**
     * Which sign-in screen a channel partner sees before any account exists — read by
     * the mobile app's public /config endpoint on the Welcome screen, before there is
     * a token to authenticate anything with. Both flows (email OTP, mobile OTP) are
     * fully built either way; this only decides which one the app opens straight to.
     */
    public function updateCpLoginMethod(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('edit-module', 'settings');

        $data = $request->validate([
            'cp_login_method' => ['required', Rule::in(['email', 'mobile'])],
        ]);

        Setting::put('cp_login_method', $data['cp_login_method']);

        return $this->settingsResponse($request, 'Channel partner sign-in method saved.');
    }

    /**
     * Replace the Firebase service account.
     *
     * Gated on manage-team, not edit-module:settings. This key can send as the entire
     * Firebase project; that is a super-admin bar, above the one for toggling a form
     * field on the same page.
     */
    public function updateFirebase(Request $request): RedirectResponse|JsonResponse
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
            ? $this->settingsResponse($request, 'Firebase service account saved. Push notifications are live.', 'success')
            : $this->settingsResponse($request, $error, 'error');
    }

    /** Removes the key. Push then no-ops and says so in the log, rather than erroring. */
    public function forgetFirebase(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('manage-team');

        FirebaseCredentials::forget();

        return $this->settingsResponse($request, 'Firebase service account removed — no push notifications will send.', 'warning');
    }

    /**
     * Proves this server can actually reach FCM, which is the half the file alone cannot
     * tell you: outbound HTTPS to Google is blocked on plenty of hosts.
     */
    public function testFirebase(Request $request, Fcm $fcm): RedirectResponse|JsonResponse
    {
        $this->authorize('manage-team');

        if (! $fcm->configured()) {
            return $this->settingsResponse($request, 'No service account is saved yet.', 'error');
        }

        // A well-formed but non-existent token: reaching FCM at all proves the OAuth2
        // exchange worked. FCM rejecting the token is the expected result.
        $result = $fcm->send(['admin-panel-probe-token'], 'Probe', 'Connectivity check');

        return $result['invalid'] === [] && $result['sent'] === 0
            ? $this->settingsResponse($request, 'Could not reach Firebase. Check outbound HTTPS from this server, then see storage/logs.', 'error')
            : $this->settingsResponse($request, 'Connected to Firebase — push notifications can be sent from this server.', 'success');
    }
}
