<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('website_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('analysis_job_id')->nullable()->index()->constrained()->cascadeOnDelete();
            $table->string('uuid', 36)->unique();
            $table->string('title', 160);
            $table->string('format', 10);
            $table->string('status', 20)->default('ready');
            $table->string('path', 255)->nullable();
            $table->string('filename', 200);
            $table->unsignedInteger('size_bytes')->nullable();
            $table->json('payload')->nullable();
            $table->string('generator', 60)->default('dompdf');
            $table->string('version', 20)->default('v1.0');
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['website_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
