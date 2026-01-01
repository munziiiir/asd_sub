<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // cust users
        Schema::create('customer_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->unique('user_id');
            $table->string('avatar', 255)->nullable();
            $table->string('name', 150)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('address_line1', 255)->nullable();
            $table->string('address_line2', 255)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('state', 120)->nullable();
            $table->string('postal_code', 40)->nullable();
            $table->string('country', 120)->nullable();
            $table->string('billing_address_line1', 255)->nullable();
            $table->string('billing_address_line2', 255)->nullable();
            $table->string('billing_city', 120)->nullable();
            $table->string('billing_state', 120)->nullable();
            $table->string('billing_postal_code', 40)->nullable();
            $table->string('billing_country', 120)->nullable();
            $table->string('card_brand', 30)->nullable();
            $table->char('card_last_four', 4)->nullable();
            $table->unsignedSmallInteger('card_exp_month')->nullable();
            $table->unsignedSmallInteger('card_exp_year')->nullable();
            $table->string('payment_customer_id', 191)->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('email');
        });

        // staff users
        Schema::create('staff_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained('hotels')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('name', 150);
            $table->string('email', 255);
            $table->dateTime('email_verified_at')->nullable();
            $table->string('password', 255);
            $table->string('remember_token', 100)->nullable();
            $table->string('role', 100); // FrontDesk/HotelManager/Admin
            $table->string('title', 100)->nullable();
            $table->string('department', 100)->nullable();
            $table->string('employment_status', 50)->nullable(); // Active/Inactive/On Leave
            $table->string('avatar', 255)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('address_line1', 255)->nullable();
            $table->string('address_line2', 255)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('state', 120)->nullable();
            $table->string('postal_code', 40)->nullable();
            $table->string('country', 120)->nullable();
            $table->dateTime('last_login_at')->nullable();
            $table->timestamps();

            $table->index('hotel_id');
            $table->index('role');
            $table->index('department');

            $table->unique('email'); // unique across ALL hotels
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE staff_users ADD CONSTRAINT staff_users_role_check CHECK (role in ('manager','frontdesk'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE staff_users DROP CHECK staff_users_role_check');
        }
        Schema::dropIfExists('customer_users');
        Schema::dropIfExists('staff_users');
    }
};
