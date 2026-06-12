<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_requests', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('email');
            $table->string('phone', 30)->nullable();
            $table->string('clinic_type')->nullable();
            $table->string('doctors_count')->nullable();
            $table->string('interest_module')->nullable();
            $table->text('message')->nullable();
            $table->enum('status', ['pending', 'contacted', 'converted', 'discarded'])->default('pending');
            $table->string('source')->nullable()->default('landing');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('contacted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('interest_module');
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_requests');
    }
};
