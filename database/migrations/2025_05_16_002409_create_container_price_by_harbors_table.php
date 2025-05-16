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
        Schema::create('container_price_by_harbors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('container_id')->nullable();
            $table->foreign('container_id')->references('id')->on('shipping_containers')->onDelete('cascade');
            $table->unsignedBigInteger('harbor_id')->nullable();
            $table->foreign('harbor_id')->references('id')->on('harbor_locations')->onDelete('cascade');
            $table->double('base_price')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('container_price_by_countries');
    }
};
