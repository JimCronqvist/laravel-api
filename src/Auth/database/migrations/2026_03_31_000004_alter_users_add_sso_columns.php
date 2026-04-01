<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('sso_provider')->nullable();
            $table->string('sso_provider_id')->nullable();
            $table->boolean('sso_policy_bypass')->default(false);

            $table->unique(['sso_provider', 'sso_provider_id']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['sso_provider', 'sso_provider_id']);
            $table->dropColumn([
                'sso_provider',
                'sso_provider_id',
                'sso_policy_bypass'
            ]);
        });
    }
};