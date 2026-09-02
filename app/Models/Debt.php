<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Debt extends Model
{
    public const TYPE_PERSONAL = 'personal';
    public const TYPE_LOAN = 'loan';
    public const TYPE_CREDIT = 'credit';
    public const TYPE_OWED_TO_USER = 'owed_to_user';
    public const TYPE_OWED_TO_OTHERS = 'owed_to_others';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAID_OFF = 'paid_off';
    public const STATUS_PAUSED = 'paused';

    public const FREQ_WEEKLY = 'weekly';
    public const FREQ_MONTHLY = 'monthly';
    public const FREQ_YEARLY = 'yearly';

    protected $fillable = [
        'user_id', 'name', 'type', 'original_amount', 'remaining_amount',
        'interest_rate', 'installment_amount', 'frequency', 'next_payment_date',
        'due_date', 'account_id', 'notes', 'status',
    ];

    protected function casts(): array
    {
        return [
            'original_amount' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
            'interest_rate' => 'decimal:2',
            'installment_amount' => 'decimal:2',
            'next_payment_date' => 'date',
            'due_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function isOwedToUser(): bool
    {
        return $this->type === self::TYPE_OWED_TO_USER;
    }
}
