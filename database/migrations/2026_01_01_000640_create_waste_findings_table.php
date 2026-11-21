<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waste_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('analysis_job_id')->constrained()->cascadeOnDelete();
            $table->string('finding_key', 60);
            $table->string('severity', 20);
            $table->string('title', 255);
            $table->json('evidence')->nullable();
            $table->text('potential_impact')->nullable();
            $table->text('correction')->nullable();
            $table->timestamps();

            $table->index(['analysis_job_id', 'severity']);
            $table->index(['website_id', 'severity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waste_findings');
    }
};
