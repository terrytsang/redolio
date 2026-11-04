<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('plan_key', 32)->unique(); // free | starter | agency
            $table->string('name');
            $table->string('tagline')->nullable();
            $table->unsignedInteger('price_monthly_cents')->default(0);
            $table->unsignedInteger('site_limit')->nullable(); // null = unlimited
            $table->unsignedInteger('auto_rescan_frequency_days')->nullable(); // null = manual rescans only
            $table->json('features_json')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
