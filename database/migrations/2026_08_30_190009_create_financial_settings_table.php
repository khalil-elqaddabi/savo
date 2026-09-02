<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('primary_currency', 3)->default('MAD');
            $table->decimal('protected_money', 15, 2)->default(0);
            $table->decimal('default_savings_rate', 5, 2)->default(20);
            $table->string('payday_day')->nullable();
            $table->boolean('safe_to_spend_enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_settings');
    }
};
