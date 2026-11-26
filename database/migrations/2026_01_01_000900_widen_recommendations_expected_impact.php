<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recommendations', function (Blueprint $table) {
            $table->string('expected_impact', 500)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('recommendations', function (Blueprint $table) {
            $table->string('expected_impact', 40)->nullable()->change();
        });
    }
};
