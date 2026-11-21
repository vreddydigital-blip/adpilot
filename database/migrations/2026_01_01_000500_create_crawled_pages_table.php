<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crawled_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_job_id')->constrained()->cascadeOnDelete();
            $table->string('url', 2048);
            $table->string('final_url', 2048)->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('title', 512)->nullable();
            $table->json('meta')->nullable();
            $table->json('headings')->nullable();
            $table->unsignedInteger('text_length')->nullable();
            $table->unsignedInteger('load_time_ms')->nullable();
            $table->string('content_hash', 64)->nullable();
            $table->json('features')->nullable();
            $table->unsignedSmallInteger('depth')->default(0);
            $table->timestamps();

            $table->index(['analysis_job_id', 'created_at']);
            $table->index('content_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crawled_pages');
    }
};
