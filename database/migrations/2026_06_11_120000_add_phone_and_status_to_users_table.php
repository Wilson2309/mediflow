<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'phone')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('phone', 30)->nullable()->after('email');
            });
        }

        if (! Schema::hasColumn('users', 'status')) {
            Schema::table('users', function (Blueprint $table) {
                $table->enum('status', ['active', 'inactive'])->default('active')->after('password');
            });
        }

        if (! Schema::hasIndex('users', 'users_clinic_id_status_index')) {
            Schema::table('users', function (Blueprint $table) {
                $table->index(['clinic_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('users', 'users_clinic_id_status_index')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex(['clinic_id', 'status']);
            });
        }

        if (Schema::hasColumn('users', 'phone')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('phone');
            });
        }

        if (Schema::hasColumn('users', 'status')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }
    }
};
