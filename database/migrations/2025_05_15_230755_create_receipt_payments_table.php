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
        Schema::create('receipt_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('from_country_id')->nullable();
            $table->foreign('from_country_id')->references('id')->on('countries')->onDelete('cascade');
            $table->unsignedBigInteger('to_country_id')->nullable();
            $table->foreign('to_country_id')->references('id')->on('countries')->onDelete('cascade');
            $table->double('original_price')->nullable();
            $table->double('after_commission_price')->nullable();
            $table->double('usd_conversion')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receipt_payments');
    }
};
