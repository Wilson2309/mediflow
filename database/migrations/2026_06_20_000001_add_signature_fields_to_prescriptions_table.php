<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->timestamp('signed_at')->nullable()->after('email_count');
            $table->foreignId('signed_by_user_id')->nullable()->after('signed_at')->constrained('users')->nullOnDelete();
            $table->string('signature_verification_code')->nullable()->unique()->after('signed_by_user_id');
            $table->string('signature_hash')->nullable()->after('signature_verification_code');
            $table->string('signed_ip_address')->nullable()->after('signature_hash');
            $table->text('signed_user_agent')->nullable()->after('signed_ip_address');
        });
    }

    public function down(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('signed_by_user_id');
            $table->dropColumn([
                'signed_at',
                'signature_verification_code',
                'signature_hash',
                'signed_ip_address',
                'signed_user_agent',
            ]);
        });
    }
};