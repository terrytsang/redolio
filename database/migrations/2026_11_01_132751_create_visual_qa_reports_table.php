<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visual_qa_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('design_spec_id')->constrained('design_specs')->cascadeOnDelete();
            $table->string('status', 16)->default('pending'); // pending | running | complete | failed
            $table->json('report_json')->nullable();
            $table->string('model_identifier')->nullable();
            $table->string('error_message')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('design_spec_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visual_qa_reports');
    }
};
