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
            $table->boolean('saved_via_search')->default(false)->after('reservation_string');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reserved_shippings', function (Blueprint $table) {
            $table->dropColumn('saved_via_search');
        });
    }
};
