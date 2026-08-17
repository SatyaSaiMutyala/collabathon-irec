<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** The authenticated principal returned by login/register/me. */
class UserResource extends JsonResource
{
    /** Every attachment column on broker_profiles — these render as full URLs, not paths. */
    private const PROFILE_FILE_COLUMNS = [
        'photo_path', 'pan_card_path', 'aadhaar_path',
        'rera_certificate_path', 'gst_path', 'cheque_path', 'signature_path',
    ];

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'mobile' => $this->mobile,
            'role' => $this->role,
            'status' => $this->status,
            // `avatar_path` is the picture a user can change later; a broker's very first
            // one is the passport photo they uploaded at registration, which lives on the
            // profile. Falling back means a broker who never edited their avatar still
            // sees themselves rather than their initials.
            'avatar_url' => $this->avatarUrl(),
            // Always this user's own record — DeveloperResource's contact gate exists
            // for a *broker* looking at a developer, not a developer looking at
            // themselves, so this is always unmasked.
            'developer' => $this->whenLoaded(
                'developer',
                fn () => (new DeveloperResource($this->developer))->withContact(true)
            ),
            'broker_profile' => $this->whenLoaded('brokerProfile'),
            // Only a mid-registration account needs this — it's what CompleteProfileScreen
            // rehydrates its form from when a 'draft' session resumes (either straight from
            // app boot, or by re-verifying OTP). registration_step is what tells the app
            // which of the 3 wizard steps to open on.
            'registration_step' => $this->when(
                $this->status === \App\Models\User::STATUS_DRAFT && $this->relationLoaded('brokerProfile'),
                fn () => $this->brokerProfile?->registration_step ?? 1,
            ),
            'draft_profile' => $this->when(
                $this->status === \App\Models\User::STATUS_DRAFT && $this->relationLoaded('brokerProfile'),
                fn () => $this->draftProfile(),
            ),
        ];
    }

    /** Reads the profile only when it is already loaded, so this never causes a query. */
    private function avatarUrl(): ?string
    {
        $path = $this->avatar_path
            ?: ($this->relationLoaded('brokerProfile') ? $this->brokerProfile?->photo_path : null);

        return $path ? asset('storage/' . $path) : null;
    }

    /**
     * Every broker_profiles column the wizard collects, snake_case as stored — the app's
     * SERVER_FIELD_TO_FORM-style mapping already knows how to translate these names, the
     * same map it uses to paint 422 errors onto fields. The 7 attachment columns come back
     * as full URLs instead of storage-relative paths, so they can go straight into
     * AttachBox/AttachPill's `uri` prop with no second lookup.
     */
    private function draftProfile(): array
    {
        $profile = $this->brokerProfile;

        $attributes = $profile?->only([
            'alternate_mobile', 'residence_address', 'photo_path',
            'is_company', 'company_name', 'office_address', 'company_website',
            'instagram', 'facebook', 'youtube', 'twitter', 'linkedin',
            'years_of_experience', 'team_size',
            'pan_card', 'pan_card_path', 'aadhaar_card', 'aadhaar_path',
            'rera_number', 'rera_certificate_path', 'rera_certificate_expiry',
            'gst_number', 'gst_path', 'cheque_details', 'cheque_path',
            'state', 'city', 'segments', 'zones', 'operates_multiple_states',
            'project_contributions', 'signature_path',
        ]) ?? [];

        foreach (self::PROFILE_FILE_COLUMNS as $column) {
            if (! empty($attributes[$column])) {
                $attributes[$column] = asset('storage/' . $attributes[$column]);
            }
        }

        return $attributes;
    }
}
