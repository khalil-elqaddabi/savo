<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialSetting extends Model
{
    protected $fillable = [
        'user_id', 'primary_currency', 'protected_money', 'default_savings_rate',
        'payday_day', 'safe_to_spend_enabled',
    ];

    protected function casts(): array
    {
        return [
            'protected_money' => 'decimal:2',
            'default_savings_rate' => 'decimal:2',
            'safe_to_spend_enabled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
