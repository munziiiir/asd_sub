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
        Schema::create('room_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('room_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('changed_by_staff_id')->nullable()->constrained('staff_users')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('assigned_staff_id')->nullable()->constrained('staff_users')->cascadeOnUpdate()->nullOnDelete();
            $table->string('context', 100)->nullable();
            $table->string('previous_status', 50)->nullable();
            $table->string('new_status', 50);
            $table->string('revert_to_status', 50)->nullable();
            $table->dateTime('revert_at')->nullable();
            $table->dateTime('reverted_at')->nullable();
            $table->text('note')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['room_id', 'revert_at']);
            $table->index('reverted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_status_logs');
    }
};
