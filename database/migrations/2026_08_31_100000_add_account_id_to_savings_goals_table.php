<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('savings_goals', function (Blueprint $table) {
            // Dedicated savings sub-account that holds the goal's allocated
            // money. A goal contribution is a transfer into this account, so
            // allocated money is real (ledger-backed) without reducing total
            // net worth.
            $table->foreignId('account_id')
                ->nullable()
                ->after('current_amount')
                ->constrained('accounts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('savings_goals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('account_id');
        });
    }
};
