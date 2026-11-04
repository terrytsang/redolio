<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Brand rename REDO → REDOLIO in the plan copy that was seeded earlier (the seeder carries the new wording for fresh installs). */
    public function up(): void
    {
        foreach (DB::table('plans')->get() as $plan) {
            $features = array_map(fn ($f) => str_replace('Publish on a REDO web address', 'Publish on a REDOLIO web address', $f), json_decode($plan->features_json, true) ?: []);
            DB::table('plans')->where('id', $plan->id)->update([
                'tagline' => str_replace('Try REDO on', 'Try REDOLIO on', (string) $plan->tagline),
                'features_json' => json_encode($features),
            ]);
        }
    }

    public function down(): void {}
};
