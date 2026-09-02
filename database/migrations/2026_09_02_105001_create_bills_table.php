<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('MAD');
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('frequency'); // daily | weekly | monthly | yearly
            $table->integer('interval')->default(1);
            $table->date('next_payment_date');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status'); // active | paused | cancelled
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'next_payment_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bills');
    }
};
