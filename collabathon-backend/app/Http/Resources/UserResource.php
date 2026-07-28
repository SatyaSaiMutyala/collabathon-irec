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
            'avatar_url' => $this->avatar_path ? asset('storage/' . $this->avatar_path) : null,
            'developer' => new DeveloperResource($this->whenLoaded('developer')),
            'broker_profile' => $this->whenLoaded('brokerProfile'),
        ];
    }
}
