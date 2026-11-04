<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('design_spec_id')->constrained('design_specs')->cascadeOnDelete();
            $table->json('evaluation_json');
            $table->string('model_identifier')->nullable();
            $table->string('prompt_version')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('design_spec_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_reports');
    }
};
