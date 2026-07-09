<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create the pivot table for many-to-many User <-> Clinic
        Schema::create('clinic_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['clinic_id', 'user_id']);
        });

        // 2. Add current_clinic_id to users (the "active" clinic they are viewing)
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('current_clinic_id')
                ->nullable()
                ->after('clinic_id')
                ->constrained('clinics')
                ->nullOnDelete();
        });

        // 3. Migrate existing data: copy clinic_id into the pivot table and current_clinic_id
        DB::statement('INSERT INTO clinic_user (clinic_id, user_id, created_at, updated_at) SELECT clinic_id, id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP FROM users WHERE clinic_id IS NOT NULL');
        DB::statement('UPDATE users SET current_clinic_id = clinic_id WHERE clinic_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_clinic_id');
        });

        Schema::dropIfExists('clinic_user');
    }
};
