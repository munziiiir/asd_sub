<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            $table->timestamp('last_password_changed_at')->nullable()->after('password');
        });

        Schema::table('staff_users', function (Blueprint $table) {
            $table->timestamp('last_password_changed_at')->nullable()->after('password');
        });

        // Seed existing rows to created_at to avoid immediate expiration.
        DB::table('admin_users')
            ->whereNull('last_password_changed_at')
            ->update(['last_password_changed_at' => DB::raw('created_at')]);

        DB::table('staff_users')
            ->whereNull('last_password_changed_at')
            ->update(['last_password_changed_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            $table->dropColumn('last_password_changed_at');
        });

        Schema::table('staff_users', function (Blueprint $table) {
            $table->dropColumn('last_password_changed_at');
        });
    }
};
