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
            $table->unsignedInteger('analysis_limit')->nullable()->after('site_limit'); // active website analyses; null = unlimited
        });
        // Free 1 watched + 1 analysis · Starter 3 + 6 · Agency 10 + 15 (also in PlanSeeder, for fresh installs)
        foreach (['free' => [1, 1, ['1 website', '1 website analysis', 'Manual analysis on demand', 'Website DNA, diagnosis & one redesign preview', 'All 5 design directions']],
            'starter' => [3, 6, ['3 websites watched', '6 active website analyses', 'Publish on a REDO web address', 'Static website code export', 'Automatic monthly re-analysis', 'Full version history', 'Next REDO improvement hypotheses']],
            'agency' => [10, 15, ['10 websites watched', '15 active website analyses', 'Publish on a REDO web address', 'Static website code export', 'Automatic weekly re-analysis', 'Priority redesign generation', 'Client-ready before/after previews']]] as $key => [$sites, $analyses, $features]) {
            DB::table('plans')->where('plan_key', $key)->update(['site_limit' => $sites, 'analysis_limit' => $analyses, 'features_json' => json_encode($features)]);
        }
    }

    public function down(): void
    {
        Schema::table('plans', fn (Blueprint $table) => $table->dropColumn('analysis_limit'));
    }
};
