<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A slug belongs to the project (analysis_run), not to one deployment
     * row — republishing must reuse the same public address across
     * versions, so a per-deployment unique constraint on slug was wrong.
     */
    public function up(): void
    {
        Schema::table('analysis_runs', function (Blueprint $table) {
            $table->string('published_slug')->nullable()->unique()->after('active_deployment_id');
        });

        Schema::table('deployments', function (Blueprint $table) {
            $table->dropUnique(['slug']);
        });
    }

    public function down(): void
    {
        Schema::table('deployments', function (Blueprint $table) {
            $table->unique('slug');
        });

        Schema::table('analysis_runs', function (Blueprint $table) {
            $table->dropColumn('published_slug');
        });
    }
};
