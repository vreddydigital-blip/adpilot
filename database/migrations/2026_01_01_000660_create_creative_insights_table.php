<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creative_insights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('analysis_job_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 60);
            $table->string('channel', 40)->nullable();
            $table->string('headline', 255)->nullable();
            $table->string('text', 500)->nullable();
            $table->integer('char_length')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['analysis_job_id', 'kind']);
            $table->index(['website_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creative_insights');
    }
};
