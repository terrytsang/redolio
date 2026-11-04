<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every payment taken through Stripe: subscription charges (first payment and renewals) and Export Pass purchases.
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 24); // subscription | export_pass
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description', 191)->nullable();
            $table->unsignedInteger('amount_cents')->default(0);
            $table->string('currency', 8)->default('usd');
            $table->string('status', 16)->default('paid');
            $table->string('stripe_session_id', 191)->nullable()->unique();
            $table->string('stripe_invoice_id', 191)->nullable()->unique();
            $table->string('stripe_payment_intent', 191)->nullable();
            $table->string('stripe_subscription_id', 191)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'paid_at']);
        });

        Schema::table('one_time_purchases', function (Blueprint $table) {
            $table->string('stripe_session_id', 191)->nullable()->unique()->after('status');
            $table->unsignedInteger('amount_cents')->nullable()->after('stripe_session_id');
        });

        // Visitor analytics: where a visit came from and on what.
        Schema::table('page_views', function (Blueprint $table) {
            $table->string('country', 80)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('device', 16)->nullable(); // mobile | tablet | desktop
            $table->string('source', 191)->nullable(); // referring site, utm_source, or "Direct"
        });
    }

    public function down(): void
    {
        Schema::table('page_views', fn (Blueprint $t) => $t->dropColumn(['country', 'city', 'device', 'source']));
        Schema::table('one_time_purchases', fn (Blueprint $t) => $t->dropColumn(['stripe_session_id', 'amount_cents']));
        Schema::dropIfExists('transactions');
    }
};
