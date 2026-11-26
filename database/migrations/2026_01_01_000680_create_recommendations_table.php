<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('analysis_job_id')->constrained()->cascadeOnDelete();
            $table->string('recommendation_key', 60);
            $table->string('priority', 20);
            $table->string('title', 255);
            $table->text('rationale')->nullable();
            $table->json('evidence')->nullable();
            $table->text('action')->nullable();
            $table->string('effort', 20)->nullable();
            $table->string('expected_impact', 40)->nullable();
            $table->string('confidence', 20)->nullable();
            $table->json('module_source')->nullable();
            $table->unsignedTinyInteger('position')->default(0);
            $table->string('source', 60)->default('rule_based_v1');
            $table->timestamps();

            $table->index(['analysis_job_id', 'priority']);
            $table->index(['website_id', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommendations');
    }
};
