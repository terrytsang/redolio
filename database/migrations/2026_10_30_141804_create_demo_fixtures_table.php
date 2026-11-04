<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_fixtures', function (Blueprint $table) {
            $table->id();
            $table->string('fixture_key')->unique();
            $table->string('display_name');
            $table->text('description');
            $table->json('fixture_payload_json');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_fixtures');
    }
};
