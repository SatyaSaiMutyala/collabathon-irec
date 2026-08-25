<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PropertyMediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'url' => $this->url ?: ($this->path ? \App\Support\FileStorage::url($this->path) : null),
            'caption' => $this->caption,
        ];
    }
}
