<?php

namespace App\Support;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;

/**
 * Shared category select-lists for the API surface.
 */
final class CategoryCatalog
{
    /**
     * System + user categories for a given owner.
     */
    public static function for(int $userId): Collection
    {
        return Category::query()
            ->where(function ($q) use ($userId) {
                $q->whereNull('user_id')->orWhere('user_id', $userId);
            })
            ->where('is_active', true)
            ->orderBy('is_system', 'desc')
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'icon', 'color', 'is_system']);
    }
}