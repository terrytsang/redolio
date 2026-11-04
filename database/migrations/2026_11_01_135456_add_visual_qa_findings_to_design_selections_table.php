<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('design_selections', function (Blueprint $table) {
            // Set when this selection was created via "Refine from these
            // findings" on a Visual QA report, so the regeneration can be
            // explicitly grounded in what actually failed, not just a
            // generic "make it better" retry.
            $table->json('visual_qa_findings_json')->nullable()->after('refine_from_design_spec_id');
        });
    }

    public function down(): void
    {
        Schema::table('design_selections', function (Blueprint $table) {
            $table->dropColumn('visual_qa_findings_json');
        });
    }
};
