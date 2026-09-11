<?php

namespace App\Http\Resources;

use App\Support\ContactMask;
use App\Support\DirectoryLocation;
use App\Support\SocialPlatforms;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The developer as the mobile app sees it.
 *
 * Both reachable channels — the public contact and the internal `key_contact_*`
 * columns — are starred until the broker viewing this has an *accepted* lead with
 * this developer, the same rule `PartnerResource` applies in the other direction
 * (`Lead::STATUS_ACCEPTED`, checked by the caller and handed in via `withContact()`).
 * A pending or nonexistent request sees masked numbers/emails on both; an accepted
 * one sees the real values on both. `key_contact_person`'s name/designation are not
 * considered sensitive on their own (no reachable channel) and are always sent.
 */
class DeveloperResource extends JsonResource
{
    private bool $contactVisible = false;

    private ?DirectoryLocation $viewedFrom = null;

    /** @param  bool  $visible  true once this developer has accepted the viewing broker's lead. */
    public function withContact(bool $visible): static
    {
        $this->contactVisible = $visible;

        return $this;
    }

    /** Where the partner reading this list is, so each row can say how far away it is. */
    public function withDistanceFrom(DirectoryLocation $from): static
    {
        $this->viewedFrom = $from;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $visible = $this->contactVisible;

        return [
            'id' => $this->id,
            'company_name' => $this->company_name,
            'contact_visible' => $visible,
            // The public point of contact, with the designation now captured alongside it.
            'contact_person' => $this->contact_person,
            'contact_designation' => $this->contact_designation,
            'mobile' => $visible ? $this->mobile : ContactMask::phone($this->mobile),
            'email' => $visible ? $this->email : ContactMask::email($this->email),
            // The internal relationship owner — name/designation are always sent (no
            // reachable channel to leak), but the number/email are gated exactly like
            // the public contact above.
            'key_contact_person' => $this->key_contact_person,
            'key_contact_designation' => $this->key_contact_designation,
            'key_contact_mobile' => $visible ? $this->key_contact_mobile : ContactMask::phone($this->key_contact_mobile),
            'key_contact_email' => $visible ? $this->key_contact_email : ContactMask::email($this->key_contact_email),
            'website' => $this->website,
            // Only the platforms this developer actually filled in, ready to render —
            // {key, label, value} per platform rather than five raw columns the app
            // would otherwise have to know the labels for itself.
            'social_links' => SocialPlatforms::linksFor($this->resource),
            'country' => $this->country,
            'city' => $this->city,
            'state' => $this->state,
            /*
             * Kilometres from the partner, straight line, once the app has told us where
             * they are. Null when it has not, or when this company has no coordinates on
             * file - the row is listed either way, just without a "12 km away" line. An
             * app build that does not know this field simply ignores it.
             */
            'distance_km' => $this->viewedFrom?->kilometresTo(
                $this->latitude === null ? null : (float) $this->latitude,
                $this->longitude === null ? null : (float) $this->longitude,
            ),
            'pincode' => $this->pincode,
            'address' => $this->address,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'logo_url' => $this->logo_path ? \App\Support\FileStorage::url($this->logo_path) : null,
            'about' => $this->about,
            'cp_payout_percent' => (float) $this->cp_payout_percent,
            'verified' => (bool) $this->verified,
            'status' => $this->status,
            // Only present when the controller eager-loaded the count — never triggers a query.
            'properties_count' => $this->whenCounted('properties'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
