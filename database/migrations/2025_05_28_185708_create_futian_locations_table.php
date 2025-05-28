<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('futian_locations', function (Blueprint $table) {
            $table->id();
            $table->string('lat')->nullable();
            $table->string('lng')->nullable();
            $table->string('image')->nullable();
            $table->string('radius')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('futian_locations');
    }
};
