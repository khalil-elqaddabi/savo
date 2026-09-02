<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    protected $fillable = [
        'user_id', 'name', 'type', 'balance', 'starting_balance', 'currency',
        'icon', 'color', 'description', 'is_active', 'is_archived', 'institution',
        'account_number_masked',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'starting_balance' => 'decimal:2',
            'is_active' => 'boolean',
            'is_archived' => 'boolean',
        ];
    }

    public static array $types = [
        'cash' => 'Cash',
        'bank' => 'Bank Account',
        'savings' => 'Savings Account',
        'credit_card' => 'Credit Card',
        'digital_wallet' => 'Digital Wallet',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'account_id');
    }

    public function outgoingTransfers(): HasMany
    {
        return $this->hasMany(Transaction::class, 'account_id')->where('type', 'transfer');
    }

    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(Transaction::class, 'destination_account_id')->where('type', 'transfer');
    }

    public function recurringTransactions(): HasMany
    {
        return $this->hasMany(RecurringTransaction::class);
    }

    public function typeLabel(): string
    {
        return self::$types[$this->type] ?? $this->type;
    }
}
