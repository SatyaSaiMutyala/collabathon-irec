<?php

namespace App\Http\Resources;

use App\Support\SocialPlatforms;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The developer as the mobile app sees it.
 *
 * `key_contact_person` and its designation/mobile/email are deliberately absent, and must
 * stay that way. That contact is the internal relationship owner — the admin panel shows
 * it, channel partners never do. This resource is the only thing standing between those
 * columns and every broker on the platform, so a field added here is published to all of
 * them; add to the admin views instead.
 */
class DeveloperResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_name' => $this->company_name,
            // The public point of contact, with the designation now captured alongside it.
            'contact_person' => $this->contact_person,
            'contact_designation' => $this->contact_designation,
            'mobile' => $this->mobile,
            'email' => $this->email,
            'website' => $this->website,
            // Only the platforms this developer actually filled in, ready to render —
            // {key, label, value} per platform rather than five raw columns the app
            // would otherwise have to know the labels for itself.
            'social_links' => SocialPlatforms::linksFor($this->resource),
            'country' => $this->country,
            'city' => $this->city,
            'state' => $this->state,
            'pincode' => $this->pincode,
            'address' => $this->address,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'rera_number' => $this->rera_number,
            'logo_url' => $this->logo_path ? asset('storage/' . $this->logo_path) : null,
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
