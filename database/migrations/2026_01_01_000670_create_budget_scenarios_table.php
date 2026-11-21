<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_scenarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('analysis_job_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('label', 120);
            $table->string('channel', 60)->default('all');
            $table->json('inputs');
            $table->json('results');
            $table->string('methodology', 80)->default('deterministic simulation');
            $table->string('version', 20)->default('v1.0');
            $table->timestamps();

            $table->index(['website_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_scenarios');
    }
};
