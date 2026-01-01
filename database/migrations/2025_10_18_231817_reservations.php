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
        // reservations
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('customer_users')->cascadeOnUpdate()->restrictOnDelete();
            $table->unsignedInteger('incremental_no')->nullable();
            $table->string('code', 50)->unique();
            $table->string('status', 32); // Pending/Confirmed/CheckedIn/NoShow/Cancelled/CheckedOut
            $table->date('check_in_date');
            $table->date('check_out_date');
            $table->unsignedSmallInteger('adults');
            $table->unsignedSmallInteger('children');
            $table->decimal('nightly_rate', 10, 2)->nullable(); // snapshot (optional)
            $table->timestamps();

            $table->index('hotel_id');
            $table->unique(['hotel_id', 'incremental_no']);
            $table->index('customer_id');
            $table->index('check_in_date');
            $table->index('check_out_date');
        });

        // reservation_room (spans per room)
        Schema::create('reservation_room', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->date('from_date');
            $table->date('to_date');
            $table->timestamps();

            $table->index('hotel_id');
            $table->index('reservation_id');
            $table->index('room_id');
            $table->index('from_date');
            $table->index('to_date');

            $table->unique(['hotel_id', 'reservation_id', 'room_id']);
        });

        // reservation_occupants (non-auth occupants)
        Schema::create('reservation_occupants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->string('full_name', 150);
            $table->unsignedTinyInteger('age')->nullable();
            $table->string('type', 20)->nullable(); // Adult/Child/Infant (optional)
            $table->timestamps();

            $table->index('reservation_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservation_occupants');
        Schema::dropIfExists('reservation_room');
        Schema::dropIfExists('reservations');
    }
};
