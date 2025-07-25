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
        Schema::table('harbor_locations', function (Blueprint $table) {
            $table->double('lat')->nullable();
            $table->double('lng')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('harbor_locations', function (Blueprint $table) {
            //
        });
    }
};
