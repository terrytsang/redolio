<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Free plan: 1 → 2 website analyses (the seeder carries the same values for fresh installs). */
    public function up(): void
    {
        $free = DB::table('plans')->where('plan_key', 'free')->first();
        if (! $free) {
            return;
        }
        $features = array_map(fn ($f) => $f === '1 website analysis' ? '2 website analyses' : $f, json_decode($free->features_json, true) ?: []);
        DB::table('plans')->where('id', $free->id)->update(['analysis_limit' => 2, 'features_json' => json_encode($features)]);
    }

    public function down(): void
    {
        DB::table('plans')->where('plan_key', 'free')->update(['analysis_limit' => 1]);
    }
};
