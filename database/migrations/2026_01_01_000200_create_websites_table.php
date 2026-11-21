<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('websites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('url', 2048);
            $table->string('normalized_url', 2048);
            $table->string('url_hash', 64);
            $table->string('host', 255);
            $table->string('label', 150)->nullable();
            $table->timestamp('last_analyzed_at')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'url_hash']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('websites');
    }
};
