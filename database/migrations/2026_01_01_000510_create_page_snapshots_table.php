<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crawled_page_id')->constrained()->cascadeOnDelete();
            $table->string('device', 20);
            $table->string('storage_path', 512);
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->timestamps();

            $table->unique(['crawled_page_id', 'device']);
            $table->index(['analysis_job_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_snapshots');
    }
};
