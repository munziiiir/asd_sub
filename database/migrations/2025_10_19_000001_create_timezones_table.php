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
        Schema::create('timezones', function (Blueprint $table) {
            $table->id();
            $table->char('country_code', 2)->index();
            $table->string('country_name', 120);
            $table->string('timezone', 191);
            $table->timestamps();

            $table->unique(['country_code', 'timezone']);
        });

        Schema::table('hotels', function (Blueprint $table) {
            $table->foreign('timezone_id')->references('id')->on('timezones')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->dropForeign(['timezone_id']);
        });
        Schema::dropIfExists('timezones');
    }
};
