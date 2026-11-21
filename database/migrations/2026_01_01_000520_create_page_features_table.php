<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crawled_page_id')->constrained()->cascadeOnDelete();
            $table->string('feature_key', 100);
            $table->boolean('present')->default(false);
            $table->unsignedInteger('count')->default(0);
            $table->json('evidence')->nullable();
            $table->timestamps();

            $table->unique(['crawled_page_id', 'feature_key']);
            $table->index(['feature_key', 'present']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_features');
    }
};
