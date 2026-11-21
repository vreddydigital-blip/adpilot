<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crawl_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_job_id')->constrained()->cascadeOnDelete();
            $table->string('url', 2048)->nullable();
            $table->string('error_type', 60);
            $table->string('message', 1000)->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();

            $table->index(['analysis_job_id', 'created_at']);
            $table->index('error_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crawl_errors');
    }
};
