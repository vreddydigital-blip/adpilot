<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('analysis_job_id')->constrained()->cascadeOnDelete();
            $table->string('module_key', 60);
            $table->unsignedTinyInteger('score')->nullable();
            $table->string('methodology', 80)->default('heuristic');
            $table->string('version', 20)->default('v1.0');
            $table->json('breakdown')->nullable();
            $table->timestamp('computed_at')->nullable();
            $table->timestamps();

            $table->unique(['analysis_job_id', 'module_key']);
            $table->index(['website_id', 'module_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_scores');
    }
};
