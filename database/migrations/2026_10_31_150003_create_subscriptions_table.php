<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * No real payment processing (no live billing provider keys are
 * configured) — a subscription record just reflects which plan a user
 * has selected. MRR on the admin dashboard is computed as the sum of
 * active subscriptions' plan price, which is accurate once/if real
 * billing is wired in later without changing this schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('plans');
            $table->string('status', 16)->default('active'); // active | canceled
            $table->timestamp('started_at');
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
