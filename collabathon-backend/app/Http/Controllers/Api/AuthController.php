<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Mail\EmailOtpMail;
use App\Models\BrokerProfile;
use App\Models\DeviceToken;
use App\Models\EmailOtpCode;
use App\Models\OtpCode;
use App\Models\Upload;
use App\Models\User;
use App\Services\AadhaarXmlReader;
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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Mobile auth. Developers sign in with email + password (Sanctum tokens). Channel
 * partners sign in with either `sendEmailOtp`/`verifyEmailOtp` (a 4-digit code emailed
 * through whichever mailer MailSettings points at — Mailjet) or `sendOtp`/`verifyOtp`
 * (a 6-digit code delivered over WhatsApp via {@see \App\Services\OtpSender} — MSG91,
 * configured through {@see \App\Support\WhatsAppSettings}) — WelcomeScreen on the
 * mobile app picks which pair to route into per the admin's `cp_login_method` setting
 * (Settings -> Channel Partners), so both stay live rather than one being dead code.
 *
 * There is no fallback delivery channel and the code is never returned to the caller:
 * if MSG91 is not configured, or the send fails, `sendOtp` answers 502 and mobile
 * sign-in is unavailable until Settings -> WhatsApp OTP is working. Configure it before
 * relying on this flow.
 *
 * A new channel partner isn't registered in one shot — `startRegistration()` (step 1)
 * then `saveRegistrationStep()` (steps 2-3) walk the account through
 * `User::STATUS_DRAFT` first, so the mobile app's 3-step wizard can save each step to
 * the database as it's completed and resume a half-finished registration later. Only
 * step 3's real submit (not a Save Draft) flips the account to `pending` — which is
 * the approval gate this class enforces regardless of which door was used: no *active*
 * token is issued until an admin approves. A rejected broker is the one exception —
 * `verifyOtp`/`verifyEmailOtp` drop them straight back into `draft` with a fresh token,
 * same shape as any other resumed registration, so they can fix what an admin rejected
 * and resubmit rather than being stuck with a dead account. That token only ever grants
 * a `draft` session (CompleteProfileScreen and nothing else) until a real step-3 submit
 * earns `pending` again — it is not the credential that unlocks the broker app itself.
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
            // Which of email/mobile this broker actually proved with an OTP just now —
            // the other one is only ever self-typed. Nullable rather than required: an
            // older app build that hasn't been updated to send it yet should still be
            // able to register, just without CompleteProfileScreen being able to lock
            // the right field on a later resume (see the migration's own docblock).
            'verified_channel' => ['nullable', 'in:email,mobile'],
            'alternate_mobile' => ['nullable', 'string', 'max:32'],
            'residence_address' => ['nullable', 'string'],
            // Optional even on a real "Next", same reasoning as before this became
            // step 1 of the wizard: a broker can finish registering without a photo.
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'email.unique' => 'This email is already registered.',
            'mobile.unique' => 'This phone number is already registered.',
        ]);

        if (! $data['save_draft'] && blank($data['residence_address'] ?? null)) {
            throw ValidationException::withMessages([
                'residence_address' => ['Enter your address of communication.'],
            ]);
        }

        $data['photo_path'] = $request->hasFile('photo')
            ? $request->file('photo')->store('broker-photos', \App\Support\FileStorage::diskName('broker-photos'))
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
                'verified_channel' => $data['verified_channel'] ?? null,
                'alternate_mobile' => $data['alternate_mobile'] ?? null,
                'residence_address' => $data['residence_address'] ?? null,
                'photo_path' => $data['photo_path'],
            ]);

            return $user;
        });

        $user->load('brokerProfile');

        return response()->json([
            'token' => $this->issueToken($user),
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

        // Own row excluded from each unique check below — resubmitting (or just
        // re-saving) this broker's own already-stored PAN/Aadhaar/RERA must never
        // flag itself as a duplicate.
        $profileId = $user->brokerProfile->id;

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
            // The path UploadController handed back for a photo already sent ahead of
            // this request — an alternative to the inline `photo` file above, not
            // both at once (see resolveUploadedPath()'s hasFile()-first precedence).
            'photo_path' => ['nullable', 'string'],

            'is_company' => ['boolean'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'office_address' => ['nullable', 'string'],
            'company_website' => ['nullable', 'string', 'max:255'],
            ...SocialPlatforms::rules(),
            'years_of_experience' => ['nullable', 'integer', 'min:0', 'max:80'],
            'team_size' => ['nullable', 'integer', 'min:0', 'max:10000'],
            // One government-issued PAN/Aadhaar, or one RERA registration, belongs to
            // one real person — enforced here so the same identity can't sit behind
            // a second channel partner account. Own row excluded via $profileId
            // (see above), same reasoning as email/mobile's own unique rules but
            // scoped to broker_profiles rather than users.
            'pan_card' => ['nullable', 'string', 'max:32', Rule::unique('broker_profiles', 'pan_card')->ignore($profileId)],
            // Set by the app after KycController::verifyPan succeeded on the typed PAN
            // number — not re-checked here. Read-only informational for the admin,
            // not a permission.
            'pan_verified' => ['nullable', 'boolean'],
            'pan_verified_name' => ['nullable', 'string', 'max:255'],
            'aadhaar_card' => ['nullable', 'string', 'max:32', Rule::unique('broker_profiles', 'aadhaar_card')->ignore($profileId)],
            // Set by the app after DigilockerController::downloadAadhaar came back
            // verified — same trust boundary as pan_verified above. The earlier
            // QR/XML/eAadhaar-upload Aadhaar endpoints (and their own verified flag)
            // were removed when Surepass's scope for those never got enabled;
            // DigiLocker is a live, UIDAI-backed check via a different product that
            // IS enabled, so this flag is back.
            'aadhaar_verified' => ['nullable', 'boolean'],
            'aadhaar_verified_name' => ['nullable', 'string', 'max:255'],
            'rera_number' => ['nullable', 'string', 'max:64', Rule::unique('broker_profiles', 'rera_number')->ignore($profileId)],
            'gst_number' => ['nullable', 'string', 'max:32'],
            // Set by the app after KycController::verifyGst succeeded on the typed
            // GSTIN — same trust boundary as pan_verified above.
            'gst_verified' => ['nullable', 'boolean'],
            'gst_verified_name' => ['nullable', 'string', 'max:255'],

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
            // as one, and `aadhaar_file` is either an eAadhaar PDF or a photo of the
            // card — no xml any more (the offline-XML upload path was removed
            // alongside KycController's Aadhaar verification endpoints).
            ...collect(self::DOCUMENTS)
                ->keys()
                ->mapWithKeys(fn ($field) => [
                    $field => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'],
                ])
                ->all(),
            // The `path` UploadController handed back for a document already sent
            // ahead of this request via POST /uploads — the whole point of that
            // endpoint: a KYC scan reaches the server as its own small request the
            // moment it's picked, so this one carries text only and stays fast and
            // reliable even on a slow connection. Same hasFile()-first precedence as
            // `photo_path` above; see resolveUploadedPath().
            ...collect(self::DOCUMENTS)
                ->values()
                ->mapWithKeys(fn ($column) => [$column => ['nullable', 'string']])
                ->all(),
        ], [
            'pan_card.unique' => 'This PAN number is already registered with another channel partner account.',
            'aadhaar_card.unique' => 'This Aadhaar number is already registered with another channel partner account.',
            'rera_number.unique' => 'This RERA number is already registered with another channel partner account.',
        ]);

        // A step's own required fields only matter when this is a real "Next"/final
        // submit — Save Draft persists whatever is present, incomplete or not.
        if (! $data['save_draft']) {
            $this->validateStepIsComplete($data['step'], $data, $user);
        }

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('broker-photos', \App\Support\FileStorage::diskName('broker-photos'));
        } elseif (filled($data['photo_path'] ?? null)) {
            $data['photo_path'] = $this->resolveUploadedPath($user, $data['photo_path']);
        }

        foreach (self::DOCUMENTS as $field => $column) {
            if ($request->hasFile($field)) {
                $data[$column] = $request->file($field)->store('broker-documents', \App\Support\FileStorage::diskName('broker-documents'));
            } elseif (filled($data[$column] ?? null)) {
                $data[$column] = $this->resolveUploadedPath($user, $data[$column]);
            }
        }

        DB::transaction(function () use ($user, $data) {
            $profile = $user->brokerProfile;

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

            // PAN verification runs off the typed number alone, not an attachment,
            // so the app reporting a fresh result is the only thing that ever
            // changes this.
            if (array_key_exists('pan_verified', $data)) {
                $panVerified = (bool) $data['pan_verified'];
                $profile->pan_verified = $panVerified;
                $profile->pan_verified_name = $panVerified ? ($data['pan_verified_name'] ?? null) : null;
                $profile->pan_verified_at = $panVerified ? now() : null;
            }

            // Same idea as pan_verified above, off the typed GSTIN alone.
            if (array_key_exists('gst_verified', $data)) {
                $gstVerified = (bool) $data['gst_verified'];
                $profile->gst_verified = $gstVerified;
                $profile->gst_verified_name = $gstVerified ? ($data['gst_verified_name'] ?? null) : null;
                $profile->gst_verified_at = $gstVerified ? now() : null;
            }

            // Same idea again, off a completed DigiLocker session rather than a
            // typed number or an attachment.
            if (array_key_exists('aadhaar_verified', $data)) {
                $aadhaarVerified = (bool) $data['aadhaar_verified'];
                $profile->aadhaar_verified = $aadhaarVerified;
                $profile->aadhaar_verified_name = $aadhaarVerified ? ($data['aadhaar_verified_name'] ?? null) : null;
                $profile->aadhaar_verified_at = $aadhaarVerified ? now() : null;
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
     * Confirms a `*_path` this request is trying to link (from POST /uploads) was
     * actually uploaded by this same user, rather than trusting the string as-is —
     * a guessed or copied path from someone else's upload must not be linkable here.
     * The path itself needs no rewriting: UploadController already stored the file
     * under the same disk/folder convention this endpoint's own inline uploads use.
     */
    private function resolveUploadedPath(User $user, string $path): string
    {
        $owned = Upload::where('user_id', $user->id)->where('path', $path)->exists();

        if (! $owned) {
            throw ValidationException::withMessages([
                'attachment' => ['One of the attached files could not be found. Please re-attach it and try again.'],
            ]);
        }

        return $path;
    }

    /**
     * The required-field gate for a real "Next"/final submit — one branch per step,
     * matching what CompleteProfileScreen's own `validateStep()` already checks
     * client-side. Kept as real required-ness here too rather than trusting the
     * client: a step's data is written to the database the moment this passes, not
     * just at the very end, so this is the only gate that actually protects it.
     *
     * Step order is Personal -> Professional -> Business, deliberately ending on the
     * heaviest step (PAN/Aadhaar/RERA/GST attachments) — the confirm-accuracy
     * agreement and the signature belong there too, right before the moment of
     * actually submitting, rather than sitting on an earlier, lighter step.
     */
    private function validateStepIsComplete(int $step, array $data, User $user): void
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

        // step === 2 — Professional info. state/city/segments/zones/
        // project_contributions/operates_multiple_states are all optional, same as
        // before this became its own step; only the company fields are required,
        // and only when registering as a company at all.
        if ($step === 2) {
            $errors = [];

            if ($data['is_company'] ?? false) {
                if (blank($data['company_name'] ?? null)) {
                    $errors['company_name'] = ['Enter company name.'];
                }
                if (blank($data['office_address'] ?? null)) {
                    $errors['office_address'] = ['Enter office address.'];
                }
            }

            if ($errors) {
                throw ValidationException::withMessages($errors);
            }

            return;
        }

        // step === 3 — Business info, plus the final agreement + signature.
        $errors = [];

        if (blank($data['pan_card'] ?? null)) {
            $errors['pan_card'] = ['Enter PAN card number.'];
        } elseif (! preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', strtoupper((string) $data['pan_card']))) {
            // Format only, not `pan_verified` — that stays the app's own signal
            // (see the note on it above, in the validate() rules) rather than a
            // hard gate here. Surepass being unreachable, or a real PAN it
            // genuinely doesn't recognise, must not block registration outright;
            // a string that was never a PAN to begin with is a different case.
            $errors['pan_card'] = ['Enter a valid PAN number, e.g. ABCDE1234F.'];
        }
        // Required for an individual broker; optional once registering as a company
        // (step 2 saved `is_company` already, and step 3's own request never resends
        // it, so this reads the persisted value rather than $data). A company isn't
        // a person with an Aadhaar of its own — its PAN/RERA already carry that
        // weight — matching the same condition CompleteProfileScreen's validateStep
        // applies client-side.
        if (! $user->brokerProfile->is_company && blank($data['aadhaar_card'] ?? null)) {
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
        $sent = $sender->send($data['mobile'], $otp->rawCode());

        // Any failed send is a hard error — MSG91 rejecting it, or the integration not
        // being configured at all. The code is never handed back to the caller, so a
        // send that did not happen has to be reported, not papered over.
        if (! $sent) {
            return response()->json([
                'message' => 'Could not send the verification code. Please try again in a moment.',
            ], 502);
        }

        return response()->json([
            'message' => 'OTP sent.',
            'expires_in' => OtpCode::TTL_MINUTES * 60,
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
            'code' => ['required', 'digits:4'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $otp = OtpCode::activeFor($data['mobile']);

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
                'token' => $this->issueToken($user, $data['device_name'] ?? null),
                'data' => new UserResource($user),
            ]);
        }

        // A rejected broker gets to fix and resubmit rather than being told a plain
        // "no" with nowhere to go — same resume mechanism as a mid-registration draft
        // (a fresh token, right back onto CompleteProfileScreen), since that's exactly
        // what this now is: registration_step is already 3 from the earlier submit
        // that got rejected, so they land straight back where the rejected data is,
        // with UserResource's rejection_reason telling them why. Step 3's own "Submit
        // for approval" (saveRegistrationStep) already flips draft -> pending on a
        // real resubmit, so nothing else about that path needs to change for this.
        if ($user->isRejected()) {
            $user->forceFill(['status' => User::STATUS_DRAFT])->save();
            $this->loadProfile($user);

            return response()->json([
                'status' => 'draft',
                'token' => $this->issueToken($user, $data['device_name'] ?? null),
                'data' => new UserResource($user),
            ]);
        }

        if (! $user->isActive()) {
            return response()->json([
                'status' => 'pending',
                'message' => match ($user->status) {
                    User::STATUS_PENDING => 'Your registration is awaiting admin approval.',
                    default => 'This account is not active.',
                },
            ], 403);
        }

        $user->forceFill(['last_login_at' => now()])->save();
        $this->loadProfile($user);

        return response()->json([
            'status' => 'login',
            'token' => $this->issueToken($user, $data['device_name'] ?? null),
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

        // Same contract as sendOtp: the code is never returned to the caller, so a send
        // that did not happen has to be reported rather than answered with "OTP sent."
        if (! $this->deliverEmailOtp($data['email'], $otp->rawCode())) {
            return response()->json([
                'message' => 'Could not send the verification code. Please try again in a moment.',
            ], 502);
        }

        return response()->json([
            'message' => 'OTP sent.',
            'expires_in' => EmailOtpCode::TTL_MINUTES * 60,
        ]);
    }

    /**
     * Queued rather than sent inline: `Mail::send()` blocks on the full round trip to
     * Mailjet (real SMTP, ~5s in practice), which is what made "Continue" on the
     * login screen sit for several seconds before the app could move to the OTP
     * screen — the code is already in the database and valid the instant
     * `issueFor()` returns, so nothing about correctness depends on the email having
     * gone out yet. `queue()` returns as soon as the job row is written, not once
     * Mailjet answers.
     *
     * This needs `php artisan queue:work` running against the `database` connection
     * (already the configured QUEUE_CONNECTION) — a job that never gets picked up
     * just sits in `jobs` forever instead of ever reaching Mailjet. A failed send
     * lands in `failed_jobs` instead of this method's own try/catch, same as any
     * other queued job in this app.
     */
    /**
     * Sends the code, or reports that it could not be sent.
     *
     * Two things were wrong here and both produced the same symptom — the API answering
     * "OTP sent." while nothing ever reached the inbox:
     *
     *  - MailSettings::apply() was never called, so the admin panel's Mailjet
     *    credentials were checked (isConfigured) but never actually installed. The send
     *    fell through to whatever MAIL_MAILER happened to be, which on a dev box is
     *    `log` — the message went into laravel.log.
     *  - queue() defers to a worker in a separate process, which boots from .env and
     *    never sees a per-request Config::set anyway. With no worker running the job
     *    simply sat in the jobs table forever.
     *
     * send(), not queue(): matches every other mailer in this codebase (see
     * ApprovalController::notifyApproved and PasswordResetController::deliver), removes
     * the worker as a dependency for a message the user is actively waiting on, and is
     * what makes the return value mean anything.
     *
     * @return bool Whether the code was actually handed to a mailer.
     */
    private function deliverEmailOtp(string $email, string $code): bool
    {
        if (! MailSettings::apply()) {
            Log::warning('Email OTP not sent — no mailer configured.', ['email' => $email]);

            return false;
        }

        try {
            Mail::to($email)->send(new EmailOtpMail($code));

            return true;
        } catch (Throwable $e) {
            Log::error('Email OTP send failed', ['email' => $email, 'error' => $e->getMessage()]);

            return false;
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
                'token' => $this->issueToken($user, $data['device_name'] ?? null),
                'data' => new UserResource($user),
            ]);
        }

        // Same resume-not-dead-end path as verifyOtp's — see its docblock.
        if ($user->isRejected()) {
            $user->forceFill(['status' => User::STATUS_DRAFT])->save();
            $this->loadProfile($user);

            return response()->json([
                'status' => 'draft',
                'token' => $this->issueToken($user, $data['device_name'] ?? null),
                'data' => new UserResource($user),
            ]);
        }

        if (! $user->isActive()) {
            return response()->json([
                'status' => 'pending',
                'message' => match ($user->status) {
                    User::STATUS_PENDING => 'Your registration is awaiting admin approval.',
                    default => 'This account is not active.',
                },
            ], 403);
        }

        $user->forceFill(['last_login_at' => now()])->save();
        $this->loadProfile($user);

        return response()->json([
            'status' => 'login',
            'token' => $this->issueToken($user, $data['device_name'] ?? null),
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
            'token' => $this->issueToken($user, $data['device_name'] ?? null),
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
     * GET /api/v1/auth/me/aadhaar-preview — a formatted read-out of the broker's
     * own DigiLocker-verified Aadhaar XML, for the mobile Profile screen's
     * Aadhaar row. The raw signed XML shows nothing readable when opened
     * directly (see ApprovalController::aadhaarPreview, the admin-panel
     * equivalent this mirrors); `status: 'unavailable'` covers both "no
     * document on file" and "it's a manually-attached photo/PDF, not the
     * DigiLocker XML" — either way the app falls back to opening the raw
     * attachment as before.
     */
    public function aadhaarPreview(Request $request): JsonResponse
    {
        $path = $request->user()->brokerProfile?->aadhaar_path;

        if (! $path || ! Str::endsWith($path, '.xml') || ! \App\Support\FileStorage::exists($path)) {
            return response()->json(['status' => 'unavailable']);
        }

        $data = AadhaarXmlReader::read(\App\Support\FileStorage::get($path));

        if ($data === null) {
            return response()->json(['status' => 'unavailable']);
        }

        return response()->json(['status' => 'available', 'data' => $data]);
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

    /**
     * Issues the Sanctum token for a sign-in, enforcing one active device per broker.
     *
     * A channel partner's account is single-device: signing in anywhere revokes every
     * token that account already held, so the previous handset's next API call comes
     * back 401 and the app drops it back to the sign-in screen. Developers are
     * deliberately exempt — a sales desk legitimately runs the same account on a phone
     * and a tablet, and this is an anti-account-sharing measure aimed at partners.
     *
     * The old handset's `device_tokens` rows go with the session. Leaving them would
     * keep pushing lead alerts to a phone that can no longer open the app, and the
     * unique index on the token means the row is recreated cleanly on the next sign-in.
     *
     * `$deviceName` becomes the token's name and is what the admin panel's CP tab
     * shows as the signed-in device, so it is worth something readable — the app sends
     * "OPPO CPH2617 · Android 15" (see utils/device.js). "mobile" is the fallback for
     * an older build that predates that.
     */
    private function issueToken(User $user, ?string $deviceName = null): string
    {
        if ($user->isBroker()) {
            $user->tokens()->delete();
            DeviceToken::where('user_id', $user->id)->delete();
        }

        return $user->createToken($deviceName ?: 'mobile')->plainTextToken;
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
