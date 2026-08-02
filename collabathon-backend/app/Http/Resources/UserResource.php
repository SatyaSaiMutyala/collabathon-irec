<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** The authenticated principal returned by login/register/me. */
class UserResource extends JsonResource
{
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
            'developer' => new DeveloperResource($this->whenLoaded('developer')),
            'broker_profile' => $this->whenLoaded('brokerProfile'),
        ];
    }

    /** Reads the profile only when it is already loaded, so this never causes a query. */
    private function avatarUrl(): ?string
    {
        $path = $this->avatar_path
            ?: ($this->relationLoaded('brokerProfile') ? $this->brokerProfile?->photo_path : null);

        return $path ? asset('storage/' . $path) : null;
    }
}
