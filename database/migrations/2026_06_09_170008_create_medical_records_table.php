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
        Schema::create('medical_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->text('personal_history')->nullable();
            $table->text('family_history')->nullable();
            $table->text('surgical_history')->nullable();
            $table->text('current_medications')->nullable();
            $table->text('chronic_diseases')->nullable();
            $table->text('observations')->nullable();
            $table->timestamps();

            $table->unique('patient_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medical_records');
    }
};
