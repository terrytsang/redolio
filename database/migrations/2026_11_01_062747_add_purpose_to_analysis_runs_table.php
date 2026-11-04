<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analysis_runs', function (Blueprint $table) {
            // 'redesign' = a normal user-submitted scan; 'direction_capture' =
            // an admin-triggered crawl used only to source a new Design
            // Direction recipe (never shown in user dashboards or admin
            // scan lists as a regular scan).
            $table->string('purpose', 32)->default('redesign')->after('source_mode');
        });
    }

    public function down(): void
    {
        Schema::table('analysis_runs', function (Blueprint $table) {
            $table->dropColumn('purpose');
        });
    }
};
