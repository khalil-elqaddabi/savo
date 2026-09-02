<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecurringResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'amount' => $this->amount,
            'frequency' => $this->frequency,
            'interval' => $this->interval,
            'account_id' => $this->account_id,
            'account' => $this->whenLoaded('account', fn () => $this->account?->name),
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn () => $this->category?->name),
            'category_icon' => $this->whenLoaded('category', fn () => $this->category?->icon),
            'next_occurrence' => $this->next_occurrence?->toDateString(),
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'is_active' => $this->is_active,
        ];
    }
}