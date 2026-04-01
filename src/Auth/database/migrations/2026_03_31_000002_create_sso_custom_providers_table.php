<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sso_custom_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('provider');

            $table->string('client_id');
            $table->text('client_secret');

            $table->string('issuer')->nullable();
            //$table->string('tenant')->nullable(); // Optional for Entra, use extra_config instead of only Entra?

            $table->string('redirect_uri')->nullable();
            $table->json('scopes')->nullable(); // Default: ['openid', 'profile', 'email']
            $table->json('extra_config')->nullable();

            $table->boolean('active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sso_custom_providers');
    }
};