<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sso_custom_provider_domains', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sso_custom_provider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sso_domain_id')->constrained()->cascadeOnDelete();

            $table->timestamps();

            $table->unique([
                'sso_custom_provider_id',
                'sso_domain_id'
            ], 'idx_sso_customer_provider_domain_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sso_custom_provider_domains');
    }
};