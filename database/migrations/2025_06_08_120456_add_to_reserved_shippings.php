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
        Schema::table('reserved_shippings', function (Blueprint $table) {
            $table->dateTime('berth_start')->nullable();
            $table->dateTime('berth_end')->nullable();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reserved_shippings', function (Blueprint $table) {
            //
        });
    }
};
