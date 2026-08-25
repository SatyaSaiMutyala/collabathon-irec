<?php

namespace App\Http\Resources;

use App\Support\ContactMask;
use App\Support\SocialPlatforms;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * One broker as a *developer* sees them — shared by the request inbox (LeadResource) and
 * the partner list (PartnerController) so the privacy rule has a single implementation.
 * Not to be confused with BrokerResource, which is the admin approvals view and is
 * deliberately far less guarded.
 *
 * Whether the reachable channels come back real or starred is not decided here: the
 * caller passes it with `withContact()`, because only the caller knows the relationship.
 * A pending request masks them; an accepted one does not.
 *
 * Never serialised at any stage, accepted or not: PAN, Aadhaar, cheque and bank details,
 * and every uploaded file (ID scans, the RERA certificate, the signature). Those are
 * identity and financial records collected for admin verification — a developer deciding
 * on a request has no reason to hold them, and Aadhaar in particular carries handling
 * restrictions a peer-to-peer screen should not inherit. GST is included because it is a
 * public business registration, not an identity document.
 */
class PartnerResource extends JsonResource
{
    private bool $contactVisible = false;

    /** @param  bool  $visible  true once the developer has accepted this broker. */
    public function withContact(bool $visible): static
    {
        $this->contactVisible = $visible;

        return $this;
    }

    /**
     * Same, for a whole page. `->each()` on the collection itself is not this — that
     * forwards to the paginator and would iterate models, leaving every resource in the
     * page still masked. The flag has to be set on the resource instances, which live on
     * the collection's own `$collection`.
     *
     * @param  mixed  $resource  anything static::collection() accepts
     */
    public static function collectionWithContact($resource, bool $visible): AnonymousResourceCollection
    {
        $collection = static::collection($resource);
        $collection->collection->each->withContact($visible);

        return $collection;
    }

    public function toArray(Request $request): array
    {
        $profile = $this->brokerProfile;
        $visible = $this->contactVisible;

        // The registration photo lives on the profile; `avatar_path` is the one the broker
        // can change later from the app. Prefer the current one, fall back to what they
        // registered with, and stay null rather than emitting a broken URL.
        $photo = $this->avatar_path ?? $profile?->photo_path;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'photo_url' => $photo ? \App\Support\FileStorage::url($photo) : null,
            'contact_visible' => $visible,

            // ---------------------------------------------------------- credentials
            'company_name' => $profile?->company_name,
            'is_company' => (bool) $profile?->is_company,
            'rera_number' => $profile?->rera_number,
            'rera_certificate_expiry' => $profile?->rera_certificate_expiry?->toDateString(),
            'gst_number' => $profile?->gst_number,
            'years_of_experience' => $profile?->years_of_experience,
            'team_size' => $profile?->team_size,
            'city' => $profile?->city,
            'state' => $profile?->state,
            'segments' => $profile?->segments,
            'zones' => $profile?->zones,
            'operates_multiple_states' => (bool) $profile?->operates_multiple_states,
            'project_contributions' => $profile?->project_contributions,
            'registered_at' => $profile?->submitted_at?->toIso8601String(),
            'member_since' => $this->created_at?->toIso8601String(),

            // ---------------------------------------------------------- gated
            // Starred rather than dropped so the developer can see the channel exists.
            'mobile' => $visible ? $this->mobile : ContactMask::phone($this->mobile),
            'email' => $visible ? $this->email : ContactMask::email($this->email),
            'alternate_mobile' => $visible
                ? $profile?->alternate_mobile
                : ContactMask::phone($profile?->alternate_mobile),

            // A URL or an address cannot be starred into something meaningful, and either
            // one is enough to reach the broker, so these are withheld until accept.
            'company_website' => $visible ? $profile?->company_website : null,
            // Empty rather than null when withheld — this is a list, and the app can
            // treat "nothing to show" the same way whether that's privacy or the broker
            // just never filled any of these in.
            'social_links' => $visible && $profile ? SocialPlatforms::linksFor($profile) : [],
            'office_address' => $visible ? $profile?->office_address : null,
            'residence_address' => $visible ? $profile?->residence_address : null,

            // ---------------------------------------------------------- list aggregates
            // Present only when the partner list selected them; the inbox does not.
            'projects_count' => $this->whenNotNull($this->projects_count),
            'last_collaborated_at' => $this->whenNotNull(
                $this->last_collaborated_at
                    ? Carbon::parse($this->last_collaborated_at)->toIso8601String()
                    : null
            ),
        ];
    }
}
