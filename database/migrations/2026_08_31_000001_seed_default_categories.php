<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Seed shared, system-level default categories. These have a NULL user_id
     * and are picked up by every user through the existing
     * `whereNull('user_id')->orWhere('user_id', $userId)` lookups, so a fresh
     * account can immediately categorize income/expenses, create category
     * budgets, and see category reports. Inserting is idempotent by name/type.
     */
    public function up(): void
    {
        $categories = [
            ['name' => 'Food & Groceries', 'type' => 'expense', 'icon' => 'shopping-cart', 'color' => '#f59e0b'],
            ['name' => 'Housing', 'type' => 'expense', 'icon' => 'home', 'color' => '#ef4444'],
            ['name' => 'Transport', 'type' => 'expense', 'icon' => 'car', 'color' => '#3b82f6'],
            ['name' => 'Utilities', 'type' => 'expense', 'icon' => 'bolt', 'color' => '#eab308'],
            ['name' => 'Entertainment', 'type' => 'expense', 'icon' => 'film', 'color' => '#8b5cf6'],
            ['name' => 'Health', 'type' => 'expense', 'icon' => 'heart-pulse', 'color' => '#ef4444'],
            ['name' => 'Shopping', 'type' => 'expense', 'icon' => 'shopping-bag', 'color' => '#ec4899'],
            ['name' => 'Education', 'type' => 'expense', 'icon' => 'graduation-cap', 'color' => '#06b6d4'],
            ['name' => 'Salary', 'type' => 'income', 'icon' => 'wallet', 'color' => '#10b981'],
            ['name' => 'Freelance', 'type' => 'income', 'icon' => 'laptop', 'color' => '#6366f1'],
            ['name' => 'Other', 'type' => 'expense', 'icon' => 'ellipsis', 'color' => '#6b7280'],
        ];

        $now = now();

        foreach ($categories as $category) {
            $exists = DB::table('categories')
                ->whereNull('user_id')
                ->where('name', $category['name'])
                ->where('type', $category['type'])
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('categories')->insert(array_merge($category, [
                'user_id' => null,
                'is_system' => true,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        DB::table('categories')->whereNull('user_id')->where('is_system', true)->delete();
    }
};
