<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class AccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $computed = Arr::get($this->additional, 'computed_balance');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'type_label' => $this->typeLabel(),
            'balance' => $computed ?? $this->balance,
            'starting_balance' => $this->starting_balance,
            'icon' => $this->icon,
            'color' => $this->color,
            'description' => $this->description,
            'currency' => $this->currency,
            'institution' => $this->institution,
            'is_active' => $this->is_active,
            'is_archived' => $this->is_archived,
            'transactions_count' => $this->whenCounted('transactions'),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}