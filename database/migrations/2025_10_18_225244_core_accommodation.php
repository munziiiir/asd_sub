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
        // hotels
        Schema::create('hotels', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('code', 50)->unique();
            $table->char('country_code', 2)->nullable()->index();
            $table->unsignedBigInteger('timezone_id')->nullable()->index();
            $table->timestamps();
        });

        // room_types
        Schema::create('room_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('name', 100);
            $table->unsignedSmallInteger('max_adults');
            $table->unsignedSmallInteger('max_children');
            $table->unsignedSmallInteger('base_occupancy');
            $table->decimal('price_off_peak', 10, 2);
            $table->decimal('price_peak', 10, 2);
            $table->timestamps();

            $table->index('hotel_id');
        });

        // rooms
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('room_type_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('number', 50);
            $table->string('floor', 50)->nullable();
            $table->string('status', 32); // Available/Occupied/Cleaning/OOS
            $table->timestamps();

            $table->index('hotel_id');
            $table->index('room_type_id');
            $table->index('status');
            $table->unique(['hotel_id', 'number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('room_types');
        Schema::dropIfExists('hotels');
    }
};
