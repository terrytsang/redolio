<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analysis_runs', function (Blueprint $table) {
            $table->boolean('is_watch_scan')->default(false)->after('source_mode'); // a scheduled rescan: not one of the person's own analyses
        });
        Schema::table('site_watches', function (Blueprint $table) {
            $table->timestamp('last_scanned_at')->nullable()->after('next_scan_at');
            $table->string('last_status', 16)->nullable()->after('last_scanned_at'); // ok | failed | running
            $table->string('last_error', 255)->nullable()->after('last_status');
        });
        Schema::create('watch_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_watch_id')->constrained('site_watches')->cascadeOnDelete();
            $table->foreignId('analysis_run_id')->nullable()->constrained('analysis_runs')->nullOnDelete();
            $table->string('status', 16)->default('ok'); // ok | failed
            $table->string('headline', 255)->nullable();
            $table->string('trend', 16)->nullable(); // baseline | improved | attention | same
            $table->json('summary_json')->nullable();
            $table->timestamp('scanned_at');
            $table->timestamps();
            $table->index(['site_watch_id', 'scanned_at']);
        });
        foreach (['starter' => ['Automatic monthly re-analysis', 'Watch reports — weekly or monthly rescans of your site'], 'agency' => ['Automatic weekly re-analysis', 'Watch reports — weekly or monthly rescans of your sites']] as $key => [$old, $new]) {
            $plan = DB::table('plans')->where('plan_key', $key)->first();
            if ($plan) {
                $features = array_map(fn ($f) => $f === $old ? $new : $f, json_decode($plan->features_json, true) ?: []);
                DB::table('plans')->where('id', $plan->id)->update(['features_json' => json_encode($features)]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('watch_reports');
        Schema::table('site_watches', fn (Blueprint $t) => $t->dropColumn(['last_scanned_at', 'last_status', 'last_error']));
        Schema::table('analysis_runs', fn (Blueprint $t) => $t->dropColumn('is_watch_scan'));
    }
};
