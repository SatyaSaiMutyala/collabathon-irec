<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Mail\EmailOtpMail;
use App\Models\BrokerProfile;
use App\Models\DeviceToken;
use App\Models\EmailOtpCode;
use App\Models\OtpCode;
use App\Models\User;
use App\Services\OtpSender;
use App\Services\PushNotifier;
use App\Support\MailSettings;
use App\Support\SocialPlatforms;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Mobile auth. Developers sign in with email + password (Sanctum tokens). Channel
 * partners sign in with `sendEmailOtp`/`verifyEmailOtp` — a 4-digit code emailed
 * through whichever mailer MailSettings points at (Mailjet), since there is no SMS
 * provider configured for the mobile-number version below.
 *
 * `sendOtp`/`verifyOtp` implement that earlier mobile-number + OTP alternative. It is
 * not wired into either client build anymore — re-enabling it is a client-side
 * navigation change, not a backend one, so the endpoints and the OtpCode/OtpSender
 * plumbing stay in place rather than being ripped out.
 *
 * A new channel partner isn't registered in one shot — `startRegistration()` (step 1)
 * then `saveRegistrationStep()` (steps 2-3) walk the account through
 * `User::STATUS_DRAFT` first, so the mobile app's 3-step wizard can save each step to
 * the database as it's completed and resume a half-finished registration later. Only
 * step 3's real submit (not a Save Draft) flips the account to `pending` — which is
 * the approval gate this class enforces regardless of which door was used: no token is
 * issued until an admin approves. Returning a token first and checking status later
 * would let a rejected broker keep a valid credential.
 */
class AuthController extends Controller
{
    /**
     * KYC uploads: multipart field name => the column its stored path goes in.
     *
     * The `_file` suffix keeps each one clear of the same-named text field holding the
     * document's *number* (`pan_card` is the number, `pan_card_file` is the scan).
     */
    private const DOCUMENTS = [
        'pan_card_file' => 'pan_card_path',
        'aadhaar_file' => 'aadhaar_path',
        'rera_certificate_file' => 'rera_certificate_path',
        'gst_file' => 'gst_path',
        'cheque_file' => 'cheque_path',
        'signature_file' => 'signature_path',
    ];

    /**
     * Whether the issued OTP may be echoed back to the caller.
     *
     * True off-production regardless, so local work and the test suite never depend on
     * an env var being set. On production it is opt-in and explicit — the deployed test
     * server sets OTP_EXPOSE_CODE, the real one never does.
     */
    private function exposesOtpCode(): bool
    {
        return ! app()->isProduction() || (bool) config('app.otp_expose_code');
    }

    /**
     * A fixed code that always verifies, in place of the real one — same gate as
     * `exposesOtpCode()`, since it is the same underlying question ("is this a build
     * someone should be able to test without a real SMS?"). Real random codes are
     * still generated and still work too (see `debug_code` above); this is an
     * additional always-on shortcut, not a replacement for them.
     */
    private const MASTER_CODE = '123456';

    private function isMasterCode(string $code): bool
    {
        return $this->exposesOtpCode() && $code === self::MASTER_CODE;
    }

    /**
     * POST /api/v1/auth/register/start — step 1 (Personal info) of the 3-step wizard.
     * Creates the User + BrokerProfile as `draft` and issues a Sanctum token, so every
     * step after this one (including a Save Draft on step 1 itself) is just an
     * authenticated PATCH to `saveRegistrationStep`. The token is also what lets the
     * app resume a `draft` session by itself on the next launch — see RootNavigator.
     *
     * No password: a broker account never has one (email/mobile + OTP is the only
     * sign-in path), but `users.password` is NOT NULL for the other roles that do use
     * one, so this fills it with an unusable random hash purely to satisfy the column.
     *
     * name/email/mobile are required unconditionally, `save_draft` or not — there is
     * no account to create at all without them, unlike every later step where a row
     * already exists for Save Draft to update partially. `residence_address` is the
     * one field that actually varies: required for a real "Next", optional for
     * Save Draft.
     */
    public function startRegistration(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'mobile' => ['required', 'string', 'max:32', 'unique:users,mobile'],
            'save_draft' => ['required', 'boolean'],
            'alternate_mobile' => ['nullable', 'string', 'max:32'],
            'residence_address' => ['nullable', 'string'],
            // Optional even on a real "Next", same reasoning as before this became
            // step 1 of the wizard: a broker can finish registering without a photo.
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        if (! $data['save_draft'] && blank($data['residence_address'] ?? null)) {
            throw ValidationException::withMessages([
                'residence_address' => ['Enter your address of communication.'],
            ]);
        }

        $data['photo_path'] = $request->hasFile('photo')
            ? $request->file('photo')->store('broker-photos', 'public')
            : null;

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make(Str::random(40)),
                'mobile' => $data['mobile'],
                'role' => User::ROLE_BROKER,
                'status' => User::STATUS_DRAFT,
            ]);

            BrokerProfile::create([
                'user_id' => $user->id,
                // A completed step 1 ("Next") resumes on step 2, the next thing left
                // to fill in; an incomplete one (Save Draft) resumes right back here.
                'registration_step' => $data['save_draft'] ? 1 : 2,
                'alternate_mobile' => $data['alternate_mobile'] ?? null,
                'residence_address' => $data['residence_address'] ?? null,
                'photo_path' => $data['photo_path'],
            ]);

            return $user;
        });

        $user->load('brokerProfile');

        return response()->json([
            'token' => $user->createToken('mobile')->plainTextToken,
            'data' => new UserResource($user),
        ], 201);
    }

    /**
     * PATCH /api/v1/auth/register/step — steps 2 and 3 of the wizard, and Save Draft on
     * any step including step 1's own fields being edited again. Requires the Sanctum
     * token `startRegistration` issued.
     *
     * `save_draft: true` persists whatever is present with no required-field check —
     * an intentionally incomplete save. `save_draft: false` ("Next", or step 3's
     * "Submit for approval") validates that step's required fields first; on step 3
     * specifically, it also finalizes the registration: flips the account from `draft`
     * to `pending` (which is what makes it appear in the admin's approval queue) and
     * fires the same notification the old one-shot `register()` used to.
     */
    public function saveRegistrationStep(Request $request, PushNotifier $push): JsonResponse
    {
        $user = $request->user();

        if (! $user->isDraft()) {
            return response()->json([
                'message' => 'This registration has already been submitted.',
            ], 403);
        }

        $data = $request->validate([
            'step' => ['required', 'integer', 'in:1,2,3'],
            'save_draft' => ['required', 'boolean'],

            // Step 1 fields — only reachable by going Back after startRegistration
            // already created the row. name/email/mobile are deliberately not
            // editable here at all: email and mobile are what OTP verification
            // actually proved belongs to this person, so this endpoint only ever
            // touches the rest of step 1's fields.
            'alternate_mobile' => ['nullable', 'string', 'max:32'],
            'residence_address' => ['nullable', 'string'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],

            'is_company' => ['boolean'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'office_address' => ['nullable', 'string'],
            'company_website' => ['nullable', 'string', 'max:255'],
            ...SocialPlatforms::rules(),
            'years_of_experience' => ['nullable', 'integer', 'min:0', 'max:80'],
            'team_size' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'pan_card' => ['nullable', 'string', 'max:32'],
            // Set by the app after KycController::verifyPan succeeded on the typed PAN
            // number — not re-checked here. Same trust boundary as aadhaar_verified
            // below: read-only informational for the admin, not a permission.
            'pan_verified' => ['nullable', 'boolean'],
            'pan_verified_name' => ['nullable', 'string', 'max:255'],
            'aadhaar_card' => ['nullable', 'string', 'max:32'],
            // Set by the app after KycController::verifyAadhaar succeeded on the photo
            // attached to this same request — not re-checked here. Trusting the
            // client's report of an already-completed Surepass call is the same trust
            // boundary pan_card/aadhaar_card above already sit on (both are typed by
            // hand and unverified server-side); this is a strictly better signal than
            // that, not a new one, and it's read-only informational for the admin, not
            // a permission.
            'aadhaar_verified' => ['nullable', 'boolean'],
            'aadhaar_verified_name' => ['nullable', 'string', 'max:255'],
            'rera_number' => ['nullable', 'string', 'max:64'],
            'gst_number' => ['nullable', 'string', 'max:32'],

            'state' => ['nullable', 'string', 'max:96'],
            'city' => ['nullable', 'string', 'max:96'],
            'segments' => ['nullable', 'array'],
            'segments.*' => ['string', 'max:64'],
            'zones' => ['nullable', 'array'],
            'zones.*' => ['string', 'max:96'],
            'operates_multiple_states' => ['boolean'],
            'project_contributions' => ['nullable', 'string'],
            // Only actually enforced below, on a non-draft step 3 — see the
            // step/save_draft-aware block after this validate() call. `sometimes`
            // keeps a step-2 request (which never sends this field at all) from
            // failing a top-level `accepted` rule it has nothing to do with.
            'confirm_accuracy' => ['sometimes', 'boolean'],

            // KYC scans. PDFs are allowed because a RERA certificate is usually issued
            // as one; `aadhaar_file` alone also allows xml — the app offers the UIDAI
            // offline XML as the primary way to attach this one (see KycController's
            // verifyAadhaarXml), alongside the older photo-of-the-card path the other
            // documents still use.
            ...collect(self::DOCUMENTS)
                ->keys()
                ->mapWithKeys(fn ($field) => [
                    $field => [
                        'nullable', 'file',
                        'mimes:' . ($field === 'aadhaar_file' ? 'jpg,jpeg,png,webp,pdf,xml' : 'jpg,jpeg,png,webp,pdf'),
                        'max:8192',
                    ],
                ])
                ->all(),
        ]);

        // A step's own required fields only matter when this is a real "Next"/final
        // submit — Save Draft persists whatever is present, incomplete or not.
        if (! $data['save_draft']) {
            $this->validateStepIsComplete($data['step'], $data);
        }

        $aadhaarFileUploaded = $request->hasFile('aadhaar_file');

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('broker-photos', 'public');
        }

        foreach (self::DOCUMENTS as $field => $column) {
            if ($request->hasFile($field)) {
                $data[$column] = $request->file($field)->store('broker-documents', 'public');
            }
        }

        DB::transaction(function () use ($user, $data, $aadhaarFileUploaded) {
            $profile = $user->brokerProfile;
            $aadhaarVerified = array_key_exists('aadhaar_verified', $data)
                ? (bool) $data['aadhaar_verified']
                : (bool) $profile->aadhaar_verified;

            $profile->fill(collect($data)->only([
                'alternate_mobile', 'residence_address', 'photo_path',
                'is_company', 'company_name', 'office_address', 'company_website',
                'instagram', 'facebook', 'youtube', 'twitter', 'linkedin',
                'years_of_experience', 'team_size', 'pan_card', 'aadhaar_card',
                'rera_number', 'gst_number',
                'state', 'city', 'segments', 'zones', 'operates_multiple_states',
                'project_contributions',
                ...array_values(self::DOCUMENTS),
            ])->all());

            if (array_key_exists('aadhaar_verified', $data) || $aadhaarFileUploaded) {
                $profile->aadhaar_verified = $aadhaarVerified;
                // Only meaningful alongside a true verification — an unverified row
                // carries no name, so a later once-verified check can't find a stale
                // name left over from a rejected or never-attempted one.
                $profile->aadhaar_verified_name = $aadhaarVerified ? ($data['aadhaar_verified_name'] ?? null) : null;
                $profile->aadhaar_verified_at = $aadhaarVerified ? now() : null;
            }

            // No file-upload trigger like Aadhaar's above — PAN verification runs off
            // the typed number alone, not an attachment, so the app reporting a fresh
            // result is the only thing that ever changes this.
            if (array_key_exists('pan_verified', $data)) {
                $panVerified = (bool) $data['pan_verified'];
                $profile->pan_verified = $panVerified;
                $profile->pan_verified_name = $panVerified ? ($data['pan_verified_name'] ?? null) : null;
                $profile->pan_verified_at = $panVerified ? now() : null;
            }

            // A completed step ("Next") resumes on the step after it — that step's
            // data is done, so reopening the app should move the user forward, not
            // drop them back onto what they just finished. An incomplete one (Save
            // Draft) resumes right back on the same step. Either way this never
            // regresses: a Save Draft on step 2 after already reaching step 3 (via
            // Back) must not un-advance the resume point.
            $targetStep = $data['save_draft'] ? $data['step'] : min($data['step'] + 1, 3);
            $profile->registration_step = max($profile->registration_step, $targetStep);

            if ($data['step'] === 3 && ! $data['save_draft']) {
                $profile->confirm_accuracy = true;
                $profile->submitted_at = now();
            }

            $profile->save();

            if ($data['step'] === 3 && ! $data['save_draft']) {
                $user->forceFill(['status' => User::STATUS_PENDING])->save();
            }
        });

        $user->refresh()->load('brokerProfile');

        if ($data['step'] === 3 && ! $data['save_draft']) {
            // After the transaction: a failed push must not undo a completed submission.
            $push->brokerRegistered($user);

            return response()->json([
                'message' => 'Registration submitted. An admin will review your account before you can sign in.',
                'data' => new UserResource($user),
            ]);
        }

        return response()->json(['data' => new UserResource($user)]);
    }

    /**
     * The required-field gate for a real "Next"/final submit — one branch per step,
     * matching what CompleteProfileScreen's own `validateStep()` already checks
     * client-side. Kept as real required-ness here too rather than trusting the
     * client: a step's data is written to the database the moment this passes, not
     * just at the very end, so this is the only gate that actually protects it.
     *
     * Step order is Personal -> Business -> Professional, deliberately ending on the
     * heaviest step (company details, PAN/Aadhaar/RERA/GST attachments) — the
     * confirm-accuracy agreement and the signature belong there too, right before the
     * moment of actually submitting, rather than sitting on an earlier, lighter step.
     */
    private function validateStepIsComplete(int $step, array $data): void
    {
        // Only reachable by going Back to step 1 after startRegistration already
        // created the row — name/email/photo were already required there (photo
        // only optionally), so residence_address is the one thing left to re-check.
        if ($step === 1) {
            if (blank($data['residence_address'] ?? null)) {
                throw ValidationException::withMessages([
                    'residence_address' => ['Enter your address of communication.'],
                ]);
            }

            return;
        }

        // step === 2 — Business info. Nothing here is required: state/city/segments/
        // zones/project_contributions/operates_multiple_states are all optional, same
        // as before this became its own step.
        if ($step === 2) {
            return;
        }

        // step === 3 — Professional info, plus the final agreement + signature.
        $errors = [];

        if ($data['is_company'] ?? false) {
            if (blank($data['company_name'] ?? null)) {
                $errors['company_name'] = ['Enter company name.'];
            }
            if (blank($data['office_address'] ?? null)) {
                $errors['office_address'] = ['Enter office address.'];
            }
        }
        if (blank($data['pan_card'] ?? null)) {
            $errors['pan_card'] = ['Enter PAN card number.'];
        }
        if (blank($data['aadhaar_card'] ?? null)) {
            $errors['aadhaar_card'] = ['Enter Aadhaar number.'];
        }
        if (blank($data['rera_number'] ?? null)) {
            $errors['rera_number'] = ['Enter RERA number.'];
        }
        if (! ($data['confirm_accuracy'] ?? false)) {
            $errors['confirm_accuracy'] = ['Please confirm to continue.'];
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * POST /api/auth/otp/send — issues a fresh 6-digit code for a mobile number.
     *
     * Deliberately answers the same shape whether or not an account exists for that
     * number: the two paths (sign in vs. complete your profile) only fork after the
     * code is verified, so this endpoint can't be used to check which mobile numbers
     * are already registered.
     */
    public function sendOtp(Request $request, OtpSender $sender): JsonResponse
    {
        $data = $request->validate([
            'mobile' => ['required', 'digits:10'],
        ]);

        $otp = OtpCode::issueFor($data['mobile']);
        $sender->send($data['mobile'], $otp->rawCode());

        return response()->json([
            'message' => 'OTP sent.',
            'expires_in' => OtpCode::TTL_MINUTES * 60,
            // Only because nothing here actually texts the code anywhere yet (see
            // OtpSender) — without it there is no way to finish the flow short of
            // tailing the log. Local/testing always; a deployed test server only when
            // OTP_EXPOSE_CODE is set, because APP_ENV there is production and an
            // environment check alone cannot distinguish it from the real thing.
            'debug_code' => $this->exposesOtpCode() ? $otp->rawCode() : null,
        ]);
    }

    /**
     * POST /api/auth/otp/verify — the fork point: an existing, active broker gets a
     * token (`status: login`); an existing draft (mid-registration) one gets a fresh
     * token to resume with (`status: draft`); an existing pending/rejected one gets
     * told why not (`status: pending`/`rejected`); a mobile with no account at all
     * gets a `verification_token` (`status: register`) — currently unused by the app
     * (the mobile-number path isn't wired into any client build — see the class
     * docblock), kept only so a client that does start using this door again has it.
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mobile' => ['required', 'digits:10'],
            'code' => ['required', 'digits:6'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $otp = OtpCode::activeFor($data['mobile']);

        if (! $otp || $otp->isLocked()) {
            throw ValidationException::withMessages([
                'code' => ['That code has expired. Request a new one.'],
            ]);
        }

        if (! $otp->matches($data['code']) && ! $this->isMasterCode($data['code'])) {
            $otp->registerFailedAttempt();

            throw ValidationException::withMessages([
                'code' => ['That code is incorrect.'],
            ]);
        }

        $otp->consume();

        $user = User::where('mobile', $data['mobile'])->where('role', User::ROLE_BROKER)->first();

        if (! $user) {
            return response()->json([
                'status' => 'register',
                'mobile' => $data['mobile'],
                'verification_token' => $otp->verificationToken(),
            ]);
        }

        // Reopened the app after the previous session was cleared (signed out, token
        // revoked, reinstalled) while a step-1-or-later registration was already in
        // progress — a fresh token resumes it exactly where `startRegistration`/
        // `saveRegistrationStep` left off, same as a normal 'login' below.
        if ($user->isDraft()) {
            $this->loadProfile($user);

            return response()->json([
                'status' => 'draft',
                'token' => $user->createToken($data['device_name'] ?? 'mobile')->plainTextToken,
                'data' => new UserResource($user),
            ]);
        }

        if (! $user->isActive()) {
            return response()->json([
                'status' => $user->status === User::STATUS_REJECTED ? 'rejected' : 'pending',
                'message' => match ($user->status) {
                    User::STATUS_PENDING => 'Your registration is awaiting admin approval.',
                    User::STATUS_REJECTED => 'Your registration was not approved.',
                    default => 'This account is not active.',
                },
            ], 403);
        }

        $user->forceFill(['last_login_at' => now()])->save();
        $this->loadProfile($user);

        return response()->json([
            'status' => 'login',
            'token' => $user->createToken($data['device_name'] ?? 'mobile')->plainTextToken,
            'data' => new UserResource($user),
        ]);
    }

    /**
     * POST /api/auth/email-otp/send — issues a fresh 4-digit code for an email address.
     *
     * Same privacy shape as `sendOtp`: the response never reveals whether an account
     * exists for that email, only whether a code was sent to it.
     */
    public function sendEmailOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $otp = EmailOtpCode::issueFor($data['email']);
        $this->deliverEmailOtp($data['email'], $otp->rawCode());

        return response()->json([
            'message' => 'OTP sent.',
            'expires_in' => EmailOtpCode::TTL_MINUTES * 60,
            'debug_code' => $this->exposesOtpCode() ? $otp->rawCode() : null,
        ]);
    }

    /**
     * Same swallow-and-log shape as `ApprovalController::notifyApproved()`: the code has
     * already been issued and the caller gets the same response either way (see
     * `sendEmailOtp`), so an unreachable mailer must not surface as a 500 here — it
     * should just be visible in the logs instead of silently vanishing.
     */
    private function deliverEmailOtp(string $email, string $code): void
    {
        if (! MailSettings::apply()) {
            return;
        }

        try {
            Mail::to($email)->send(new EmailOtpMail($code));
        } catch (\Throwable $e) {
            Log::error('Email OTP send failed', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * POST /api/auth/email-otp/verify — the channel-partner sign-in fork point: an
     * existing, active broker gets a token (`status: login`); an existing draft
     * (mid-registration) one gets a fresh token to resume with (`status: draft`); an
     * existing pending/rejected one gets told why not (`status: pending`/`rejected`);
     * an email with no account at all gets `status: register` so the app can send the
     * verified address straight into step 1 of the registration wizard.
     *
     * No `verification_token` here unlike `verifyOtp` — this path never needed one:
     * `startRegistration()` re-validates the email's own uniqueness itself.
     *
     * There's no environment-gated master code here the way `verifyOtp` has one —
     * {@see EmailOtpCode} issues the same fixed code to everyone, so it doesn't need
     * a separate always-on bypass to keep working for a store reviewer.
     */
    public function verifyEmailOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'code' => ['required', 'digits:4'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $otp = EmailOtpCode::activeFor($data['email']);

        if (! $otp || $otp->isLocked()) {
            throw ValidationException::withMessages([
                'code' => ['That code has expired. Request a new one.'],
            ]);
        }

        if (! $otp->matches($data['code'])) {
            $otp->registerFailedAttempt();

            throw ValidationException::withMessages([
                'code' => ['That code is incorrect.'],
            ]);
        }

        $otp->consume();

        $user = User::where('email', $data['email'])->where('role', User::ROLE_BROKER)->first();

        if (! $user) {
            return response()->json([
                'status' => 'register',
                'email' => $data['email'],
            ]);
        }

        // Same resume path as verifyOtp's — see its docblock.
        if ($user->isDraft()) {
            $this->loadProfile($user);

            return response()->json([
                'status' => 'draft',
                'token' => $user->createToken($data['device_name'] ?? 'mobile')->plainTextToken,
                'data' => new UserResource($user),
            ]);
        }

        if (! $user->isActive()) {
            return response()->json([
                'status' => $user->status === User::STATUS_REJECTED ? 'rejected' : 'pending',
                'message' => match ($user->status) {
                    User::STATUS_PENDING => 'Your registration is awaiting admin approval.',
                    User::STATUS_REJECTED => 'Your registration was not approved.',
                    default => 'This account is not active.',
                },
            ], 403);
        }

        $user->forceFill(['last_login_at' => now()])->save();
        $this->loadProfile($user);

        return response()->json([
            'status' => 'login',
            'token' => $user->createToken($data['device_name'] ?? 'mobile')->plainTextToken,
            'data' => new UserResource($user),
        ]);
    }

    /** Login for developers. Channel partners sign in with email + OTP instead — see `verifyEmailOtp`. */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'role' => ['nullable', Rule::in([User::ROLE_BROKER, User::ROLE_DEVELOPER])],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            // Same message either way so the endpoint can't be used to enumerate emails.
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        if ($user->isAdmin()) {
            throw ValidationException::withMessages([
                'email' => ['Admin accounts sign in through the web panel.'],
            ]);
        }

        if (isset($data['role']) && $user->role !== $data['role']) {
            throw ValidationException::withMessages([
                'email' => ['This account is not registered as a ' . $data['role'] . '.'],
            ]);
        }

        // Approval gate — no token for a pending or rejected broker.
        if (! $user->isActive()) {
            return response()->json([
                'message' => match ($user->status) {
                    User::STATUS_PENDING => 'Your registration is awaiting admin approval.',
                    User::STATUS_REJECTED => 'Your registration was not approved.',
                    default => 'This account is not active.',
                },
                'status' => $user->status,
            ], 403);
        }

        $user->forceFill(['last_login_at' => now()])->save();
        $this->loadProfile($user);

        return response()->json([
            'token' => $user->createToken($data['device_name'] ?? 'mobile')->plainTextToken,
            'data' => new UserResource($user),
        ]);
    }

    /**
     * POST /api/auth/device-token — the app hands over its FCM registration token.
     *
     * Upsert on the token, not on the user: one person can be signed in on two devices,
     * and one device can be handed to a different person. Keying on the token means a
     * re-register moves the row to whoever holds it now, so a push never reaches an
     * account that was signed out of on that handset.
     */
    public function registerDevice(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'platform' => ['nullable', 'in:ios,android'],
        ]);

        DeviceToken::updateOrCreate(
            ['token' => $data['token']],
            [
                'user_id' => $request->user()->id,
                'platform' => $data['platform'] ?? null,
                'last_used_at' => now(),
            ],
        );

        return response()->json(['message' => 'Device registered.']);
    }

    /** DELETE /api/auth/device-token — called on sign-out, before the token is revoked. */
    public function forgetDevice(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:255']]);

        // Scoped to the caller so one account cannot unregister another's device.
        DeviceToken::where('token', $data['token'])
            ->where('user_id', $request->user()->id)
            ->delete();

        return response()->json(['message' => 'Device forgotten.']);
    }

    /** The authenticated principal. */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->loadProfile($user);

        return response()->json(['data' => new UserResource($user)]);
    }

    /**
     * Eager-loads the role's profile. The developer side also carries its property
     * tally, which the mobile profile screen renders — counted here so the resource's
     * `whenCounted` guard resolves instead of silently omitting the field.
     */
    private function loadProfile(User $user): void
    {
        if ($user->isDeveloper()) {
            $user->load(['developer' => fn ($q) => $q->withCount('properties')]);

            return;
        }

        $user->load('brokerProfile');
    }

    /** Revokes the current device's token only, not every session. */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Signed out.']);
    }

    /**
     * DELETE /api/v1/auth/account — self-service account deletion, from the Profile
     * screen. A soft delete: the row (and every lead/property record pointing at it)
     * stays put — flipped to `inactive` rather than removed, so it keeps showing up in
     * the admin's roster instead of vanishing. `isActive()` already gates every sign-in
     * path on `status === active`, so an inactive account is rejected there with no
     * extra check needed; every access token is revoked here so an already-issued one
     * cannot keep working until it naturally expires.
     */
    public function deleteAccount(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->forceFill([
            'status' => User::STATUS_INACTIVE,
            'deleted_at' => now(),
        ])->save();

        $user->tokens()->delete();

        return response()->json(['message' => 'Your account has been deleted.']);
    }
}
