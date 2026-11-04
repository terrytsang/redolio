<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedInteger('ai_image_limit')->nullable()->after('analysis_limit'); // null = unlimited
            $table->string('ai_image_period', 8)->default('month')->after('ai_image_limit'); // total | month
        });

        // One row per successful generation: the quota is counted from here.
        Schema::create('ai_image_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('analysis_run_id')->nullable()->constrained()->nullOnDelete();
            $table->string('prompt', 600);
            $table->string('asset_key', 64)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'created_at']);
        });

        $feature = ['free' => '2 AI-generated images (Meta Muse Image)', 'starter' => '5 AI-generated images every month', 'agency' => '5 AI-generated images every month'];
        foreach (['free' => [2, 'total'], 'starter' => [5, 'month'], 'agency' => [5, 'month']] as $key => [$limit, $period]) {
            $plan = DB::table('plans')->where('plan_key', $key)->first();
            if (! $plan) {
                continue;
            }
            $features = json_decode($plan->features_json, true) ?: [];
            if (! in_array($feature[$key], $features, true)) {
                array_splice($features, $key === 'free' ? count($features) : max(0, count($features) - (int) ($key === 'starter' ? 2 : 2)), 0, [$feature[$key]]);
            }
            DB::table('plans')->where('id', $plan->id)->update(['ai_image_limit' => $limit, 'ai_image_period' => $period, 'features_json' => json_encode($features)]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_image_generations');
        Schema::table('plans', fn (Blueprint $t) => $t->dropColumn(['ai_image_limit', 'ai_image_period']));
    }
};
