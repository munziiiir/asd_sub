<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customer_users', function (Blueprint $table) {
            $table->string('card_number_hash', 191)->nullable()->after('card_exp_year');
            $table->string('card_cvv_hash', 191)->nullable()->after('card_number_hash');
        });
    }

    public function down(): void
    {
        Schema::table('customer_users', function (Blueprint $table) {
            $table->dropColumn(['card_number_hash', 'card_cvv_hash']);
        });
    }
};
