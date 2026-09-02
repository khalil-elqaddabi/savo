<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetShare extends Model
{
    public const ROLE_VIEWER = 'viewer';
    public const ROLE_EDITOR = 'editor';

    protected $fillable = [
        'budget_id', 'user_id', 'role',
    ];

    protected function casts(): array
    {
        return [
            'role' => 'string',
        ];
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
