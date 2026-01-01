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
        // check_ins
        Schema::create('check_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('handled_by')->nullable()->constrained('staff_users')->restrictOnDelete();
            $table->timestamp('checked_in_at');
            $table->boolean('identity_verified')->default(false);
            $table->string('identity_document_type', 100)->nullable();
            $table->string('identity_document_number', 120)->nullable();
            $table->text('identity_notes')->nullable();
            $table->decimal('preauth_amount', 10, 2)->nullable();
            $table->string('preauth_method', 100)->nullable();
            $table->string('preauth_reference', 191)->nullable();
            $table->string('preauth_status', 50)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index('reservation_id');
            $table->index('room_id');
            $table->index('handled_by');
            $table->index('checked_in_at');
        });

        // check_outs
        Schema::create('check_outs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('handled_by')->nullable()->constrained('staff_users')->restrictOnDelete();
            $table->timestamp('checked_out_at');
            $table->decimal('room_charges_total', 10, 2)->default(0);
            $table->json('extras_breakdown')->nullable();
            $table->decimal('extras_total', 10, 2)->default(0);
            $table->decimal('grand_total', 10, 2)->default(0);
            $table->string('final_payment_method', 100)->nullable();
            $table->string('final_payment_reference', 191)->nullable();
            $table->string('final_payment_status', 50)->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('reservation_id');
            $table->index('room_id');
            $table->index('handled_by');
            $table->index('checked_out_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('check_outs');
        Schema::dropIfExists('check_ins');
    }
};
