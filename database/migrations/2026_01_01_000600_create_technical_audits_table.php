<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technical_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_job_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('website_id')->index()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('score')->nullable();
            $table->json('checks')->nullable();
            $table->string('version', 20)->default('v1.0');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technical_audits');
    }
};
