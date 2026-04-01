<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sso_domains', function (Blueprint $table) {
            $table->id();
            $table->string('domain')->unique();
            $table->boolean('verified')->default(false);
            $table->enum('login_mode', ['sso_optional', 'sso_required']);
            $table->json('allowed_providers')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sso_domains');
    }
};