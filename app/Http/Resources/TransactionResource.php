<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'amount' => $this->amount,
            'date' => $this->date->toDateString(),
            'description' => $this->description,
            'merchant' => $this->merchant,
            'account_id' => $this->account_id,
            'account' => $this->whenLoaded('account', fn () => $this->account?->name),
            'destination_account_id' => $this->destination_account_id,
            'destination' => $this->whenLoaded('destinationAccount', fn () => $this->destinationAccount?->name),
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn () => $this->category?->name),
            'category_icon' => $this->whenLoaded('category', fn () => $this->category?->icon),
            'category_color' => $this->whenLoaded('category', fn () => $this->category?->color),
        ];
    }
}