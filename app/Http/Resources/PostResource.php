<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'scheduled_for' => $this->scheduled_for?->toIso8601String(),
            'status' => $this->status->value,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'targets' => PostTargetResource::collection($this->whenLoaded('targets')),
            'variants' => PostVariantResource::collection($this->whenLoaded('variants')),
            'media' => PostMediaResource::collection($this->whenLoaded('media')),
        ];
    }
}
