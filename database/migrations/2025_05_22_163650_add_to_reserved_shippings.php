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
            $table->string('track_number')->nullable();
            $table->integer('count')->default(1);
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
