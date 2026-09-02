<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Smart notification persisted in the standard Laravel notifications table.
 *
 * Reuses the framework's notification storage (the User model already uses the
 * Notifiable trait and a `notifications` table is now provisioned). This model
 * is only a typed query/read wrapper over that same table so the frontend can
 * list, mark-as-read and acknowledge in-app notifications without swapping in a
 * second notification system.
 */
class Notification extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $primaryKey = 'id';

    protected $fillable = [
        'id', 'notifiable_id', 'notifiable_type', 'type', 'kind', 'title',
        'message', 'related_type', 'related_id', 'data', 'read_at',
    ];

    protected function casts(): array
    {
        return [
            'notifiable_id' => 'integer',
            'related_id' => 'integer',
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('notifiable_type', User::class)->where('notifiable_id', $userId);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function scopeOfKind(Builder $query, string $kind): Builder
    {
        return $query->where('kind', $kind);
    }
}
