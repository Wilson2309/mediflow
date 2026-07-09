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
        Schema::table('clinics', function (Blueprint $table) {
            $table->string('legal_name')->nullable()->after('name');
            $table->string('legal_representative')->nullable()->after('ruc');
            $table->string('secondary_phone')->nullable()->after('phone');
            $table->string('website')->nullable()->after('email');
            $table->string('clinic_type')->nullable()->after('website');
            $table->string('country')->nullable()->after('address');
            $table->string('state')->nullable()->after('country');
            $table->string('city')->nullable()->after('state');
            $table->string('logo_path')->nullable()->after('city');
            $table->string('subscription_plan')->default('basic')->after('status');
            $table->date('subscription_end_date')->nullable()->after('subscription_plan');
            $table->text('internal_notes')->nullable()->after('subscription_end_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            $table->dropColumn([
                'legal_name',
                'legal_representative',
                'secondary_phone',
                'website',
                'clinic_type',
                'country',
                'state',
                'city',
                'logo_path',
                'subscription_plan',
                'subscription_end_date',
                'internal_notes'
            ]);
        });
    }
};
