<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 10)->default('fr')->after('password');
            $table->string('theme', 10)->default('light')->after('locale');
            $table->string('currency', 3)->default('MAD')->after('theme');
            $table->string('phone')->nullable()->after('currency');
            $table->timestamp('two_factor_feature_disabled_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['locale', 'theme', 'currency', 'phone', 'two_factor_feature_disabled_at']);
        });
    }
};
