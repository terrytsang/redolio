<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id', 64)->nullable()->unique()->after('email');
            $table->string('stripe_customer_id', 64)->nullable()->after('google_id');
        });
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('stripe_subscription_id', 64)->nullable()->unique()->after('plan_id');
            $table->timestamp('current_period_end')->nullable()->after('canceled_at');
            $table->boolean('cancel_at_period_end')->default(false)->after('current_period_end');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['stripe_subscription_id', 'current_period_end', 'cancel_at_period_end']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['google_id', 'stripe_customer_id']);
        });
    }
};
