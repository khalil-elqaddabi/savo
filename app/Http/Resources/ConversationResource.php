<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'message_count' => $this->whenCounted('messages'),
            'updated_at' => $this->updated_at?->toDateTimeString(),
            'updated_at_human' => $this->updated_at?->diffForHumans(),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}