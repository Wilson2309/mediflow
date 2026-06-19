<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->timestamp('last_printed_at')->nullable()->after('status');
            $table->unsignedInteger('print_count')->default(0)->after('last_printed_at');
            $table->timestamp('last_emailed_at')->nullable()->after('print_count');
            $table->string('last_emailed_to')->nullable()->after('last_emailed_at');
            $table->unsignedInteger('email_count')->default(0)->after('last_emailed_to');
        });
    }

    public function down(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropColumn([
                'last_printed_at',
                'print_count',
                'last_emailed_at',
                'last_emailed_to',
                'email_count',
            ]);
        });
    }
};
