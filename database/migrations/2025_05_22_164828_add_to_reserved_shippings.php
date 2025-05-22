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
            $table->string('carrier_code')->nullable()->after('track_number'); // e.g. MSK, COSCO
    $table->string('port_code')->nullable()->after('carrier_code');   // e.g. CNNGB
    $table->enum('is_export', ['E', 'I'])->default('E')->after('port_code');
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
