<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\BrokerProfile;
use App\Models\DeviceToken;
use App\Models\User;
use App\Services\PushNotifier;
use App\Support\SocialPlatforms;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Mobile auth. Email + password (Sanctum tokens) for both mobile roles.
 *
 * The approval gate lives here: a broker registers as `pending` and is issued NO token
 * until an admin approves. Returning a token first and checking status later would let a
 * rejected broker keep a valid credential.
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

    /** Broker self-registration. Creates the user + profile, issues no token. */
    public function register(Request $request, PushNotifier $push): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'mobile' => ['required', 'string', 'max:32'],

            'alternate_mobile' => ['nullable', 'string', 'max:32'],
            'residence_address' => ['nullable', 'string'],
            'is_company' => ['boolean'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'office_address' => ['nullable', 'string'],
            'company_website' => ['nullable', 'string', 'max:255'],
            ...SocialPlatforms::rules(),
            'years_of_experience' => ['nullable', 'integer', 'min:0', 'max:80'],
            'team_size' => ['nullable', 'integer', 'min:0', 'max:10000'],

            'pan_card' => ['nullable', 'string', 'max:32'],
            'aadhaar_card' => ['nullable', 'string', 'max:32'],
            'rera_number' => ['nullable', 'string', 'max:64'],
            'rera_certificate_expiry' => ['nullable', 'date'],
            'gst_number' => ['nullable', 'string', 'max:32'],
            'cheque_details' => ['nullable', 'string', 'max:255'],

            'state' => ['nullable', 'string', 'max:96'],
            'city' => ['nullable', 'string', 'max:96'],
            'segments' => ['nullable', 'array'],
            'segments.*' => ['string', 'max:64'],
            'zones' => ['nullable', 'array'],
            'zones.*' => ['string', 'max:96'],
            'operates_multiple_states' => ['boolean'],
            'project_contributions' => ['nullable', 'string'],
            'confirm_accuracy' => ['accepted'],

            // The passport photo the registration form collects. Optional, because the
            // form lets a broker submit without one — but until this existed the field
            // was gathered in the app and thrown away, so every broker's profile fell
            // back to initials no matter what they uploaded.
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],

            // KYC scans. Same failure the photo had: the app made these attachments
            // required, uploaded nothing, and the admin's Documents panel read
            // "Not provided" for every registration. PDFs are allowed because a RERA
            // certificate is usually issued as one.
            ...collect(self::DOCUMENTS)
                ->keys()
                ->mapWithKeys(fn ($field) => [
                    $field => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'],
                ])
                ->all(),
        ]);

        // Stored before the transaction so a failed insert cannot leave a half-written
        // row pointing at nothing; an orphaned file is the cheaper failure.
        $data['photo_path'] = $request->hasFile('photo')
            ? $request->file('photo')->store('broker-photos', 'public')
            : null;

        foreach (self::DOCUMENTS as $field => $column) {
            $data[$column] = $request->hasFile($field)
                ? $request->file($field)->store('broker-documents', 'public')
                : null;
        }

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'mobile' => $data['mobile'],
                'role' => User::ROLE_BROKER,
                'status' => User::STATUS_PENDING,
            ]);

            BrokerProfile::create(collect($data)->only([
                'alternate_mobile', 'residence_address', 'is_company', 'company_name',
                'office_address', 'company_website',
                'instagram', 'facebook', 'youtube', 'twitter', 'linkedin',
                'years_of_experience', 'team_size', 'pan_card', 'aadhaar_card',
                'rera_number', 'rera_certificate_expiry', 'gst_number', 'cheque_details',
                'state', 'city', 'segments', 'zones', 'operates_multiple_states',
                'project_contributions', 'photo_path',
                ...array_values(self::DOCUMENTS),
            ])->merge([
                'user_id' => $user->id,
                'confirm_accuracy' => true,
                'submitted_at' => now(),
            ])->all());

            return $user;
        });

        // Without the relation loaded, UserResource cannot reach the passport photo it
        // falls back to, so a registration that uploaded one still answered avatar_url
        // null and the app showed initials for the picture it had just accepted.
        //
        // Only the columns the avatar needs: loading the whole row would echo the entire
        // KYC record — PAN, Aadhaar, cheque and signature paths — back out of an endpoint
        // whose job is to confirm a submission.
        $user->load(['brokerProfile' => fn ($query) => $query->select('id', 'user_id', 'photo_path')]);

        // After the transaction: a failed push must not undo a completed registration.
        $push->brokerRegistered($user);

        return response()->json([
            'message' => 'Registration submitted. An admin will review your account before you can sign in.',
            'data' => new UserResource($user),
        ], 201);
    }

    /** Login for brokers and developers. */
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
}
