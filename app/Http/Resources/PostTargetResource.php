<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostTargetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'social_account_id' => $this->social_account_id,
            'status' => $this->status->value,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'error_message' => $this->error_message,
            'social_account' => new SocialAccountResource($this->whenLoaded('socialAccount')),
        ];
    }
}
