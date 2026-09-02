<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Budget extends Model
{
    public const PERIOD_WEEKLY = 'weekly';
    public const PERIOD_MONTHLY = 'monthly';
    public const SCOPE_OVERALL = 'overall';
    public const SCOPE_CATEGORY = 'category';

    protected $fillable = [
        'user_id', 'name', 'scope', 'category_id', 'period', 'amount',
        'started_at', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_active' => 'boolean',
            'started_at' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Users this budget has been shared with (excludes the owner).
     */
    public function members(): HasMany
    {
        return $this->hasMany(BudgetShare::class);
    }

    /**
     * Whether the given user may view this budget (owner or a member).
     */
    public function hasAccess(int $userId): bool
    {
        return (int) $this->user_id === $userId
            || $this->members()->where('user_id', $userId)->exists();
    }

    /**
     * Ids of every user this budget aggregates spending across (owner + members).
     *
     * @return int[]
     */
    public function spendingUserIds(): array
    {
        $ids = [$this->user_id];
        foreach ($this->members()->pluck('user_id') as $memberId) {
            $ids[] = (int) $memberId;
        }

        return array_values(array_unique($ids));
    }
}
