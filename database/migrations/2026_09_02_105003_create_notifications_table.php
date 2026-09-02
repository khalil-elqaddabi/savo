<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Standard Laravel notifications table, extended with the fields Savo's
        // smart notifications need: a human `kind`, `title`, `message` and an
        // optional related entity. Keeping it compatible with the Notifiable
        // trait (notifiable_id / notifiable_type / type / data / read_at) means
        // the existing notification infrastructure can be reused untouched.
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('notifiable_id');
            $table->string('notifiable_type');
            $table->string('type'); // notification class (e.g. App\Notifications\SmartNotification)
            $table->string('kind')->nullable(); // budget_alert | upcoming_bill | goal_progress | unusual_spending ...
            $table->string('title')->nullable();
            $table->text('message')->nullable();
            $table->string('related_type')->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->text('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['notifiable_type', 'notifiable_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
