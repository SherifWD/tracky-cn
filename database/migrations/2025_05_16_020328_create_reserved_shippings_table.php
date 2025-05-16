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
        Schema::create('reserved_shippings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('container_id')->nullable();
            $table->foreign('container_id')->references('id')->on('shipping_containers')->onDelete('cascade');
            $table->unsignedBigInteger('harbor_id_from')->nullable();
            $table->foreign('harbor_id_from')->references('id')->on('harbor_locations')->onDelete('cascade');
            $table->unsignedBigInteger('harbor_id_to')->nullable();
            $table->foreign('harbor_id_to')->references('id')->on('harbor_locations')->onDelete('cascade');
            $table->unsignedBigInteger('container_price_id')->nullable();
            $table->foreign('container_price_id')->references('id')->on('container_price_by_countries')->onDelete('cascade');
            $table->date('date')->nullable();
            $table->double('base_price')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reserved_shippings');
    }
};
