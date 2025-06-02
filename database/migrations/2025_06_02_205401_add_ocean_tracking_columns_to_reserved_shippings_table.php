<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::table('reserved_shippings', function (Blueprint $table) {
        // From Port Data API
        $table->string('subscription_id')->nullable()->after('reservation_string');
        $table->string('vessel_name')->nullable();
        $table->string('voyage')->nullable();
        $table->string('imo_number')->nullable();
        $table->string('call_sign')->nullable();
        $table->string('terminal_code')->nullable();
        $table->string('terminal_name')->nullable();
        $table->dateTime('eta')->nullable();
        $table->dateTime('etd')->nullable();
        $table->dateTime('ata')->nullable();
        $table->dateTime('atd')->nullable();

        // From Ship Location API
        $table->string('ship_name')->nullable();
        $table->decimal('ship_lat', 10, 6)->nullable();
        $table->decimal('ship_lon', 10, 6)->nullable();
        $table->string('ship_status')->nullable();
        $table->integer('ship_speed')->nullable();
        $table->string('ship_eta')->nullable(); // This may be in HH-MM format, keep as string
    });
}

public function down()
{
    Schema::table('reserved_shippings', function (Blueprint $table) {
        $table->dropColumn([
            'subscription_id',
            'vessel_name',
            'voyage',
            'imo_number',
            'call_sign',
            'terminal_code',
            'terminal_name',
            'eta',
            'etd',
            'ata',
            'atd',
            'ship_name',
            'ship_lat',
            'ship_lon',
            'ship_status',
            'ship_speed',
            'ship_eta',
        ]);
    });
}

};
