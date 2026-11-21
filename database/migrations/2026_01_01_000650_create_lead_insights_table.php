<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_insights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('analysis_job_id')->constrained()->cascadeOnDelete();
            $table->string('insight_key', 60);
            $table->string('priority', 20);
            $table->string('title', 255);
            $table->json('evidence')->nullable();
            $table->text('recommendation')->nullable();
            $table->string('effort', 20)->nullable();
            $table->timestamps();

            $table->index(['analysis_job_id', 'priority']);
            $table->index(['website_id', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_insights');
    }
};
