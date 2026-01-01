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
        // folios
        Schema::create('folios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('folio_no', 50)->unique();
            $table->string('status', 20); // Open/Closed
            $table->timestamps();

            $table->index('reservation_id');
            $table->index('status');
        });

        // charges
        Schema::create('charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folio_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->date('post_date');
            $table->string('description', 255);
            $table->decimal('qty', 10, 2)->default(1.00);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('tax_amount', 10, 2)->default(0.00);
            $table->decimal('total_amount', 10, 2);
            $table->timestamps();

            $table->index('folio_id');
            $table->index('post_date');
        });

        // payments
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folio_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('method', 32); // Cash/Card/Transfer
            $table->decimal('amount', 10, 2);
            $table->string('txn_ref', 100)->nullable();
            $table->timestamp('paid_at');
            $table->timestamps();

            $table->index('folio_id');
            $table->index('method');
            $table->index('paid_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('charges');
        Schema::dropIfExists('folios');
    }
};
