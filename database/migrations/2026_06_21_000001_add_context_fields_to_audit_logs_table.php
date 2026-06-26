<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('audit_logs', 'clinic_id')) {
                $table->foreignId('clinic_id')->nullable()->after('id')->constrained('clinics')->nullOnDelete();
                $table->index('clinic_id', 'audit_logs_clinic_id_index');
            }

            if (! Schema::hasColumn('audit_logs', 'auditable_type')) {
                $table->string('auditable_type')->nullable()->after('action');
            }

            if (! Schema::hasColumn('audit_logs', 'auditable_id')) {
                $table->unsignedBigInteger('auditable_id')->nullable()->after('auditable_type');
            }

            if (! Schema::hasColumn('audit_logs', 'old_values')) {
                $table->json('old_values')->nullable()->after('description');
            }

            if (! Schema::hasColumn('audit_logs', 'new_values')) {
                $table->json('new_values')->nullable()->after('old_values');
            }
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['auditable_type', 'auditable_id'], 'audit_logs_auditable_index');
            $table->index('created_at', 'audit_logs_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_logs_auditable_index');
            $table->dropIndex('audit_logs_created_at_index');

            if (Schema::hasColumn('audit_logs', 'clinic_id')) {
                $table->dropForeign(['clinic_id']);
                $table->dropIndex('audit_logs_clinic_id_index');
            }
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $columns = array_filter([
                Schema::hasColumn('audit_logs', 'clinic_id') ? 'clinic_id' : null,
                Schema::hasColumn('audit_logs', 'auditable_type') ? 'auditable_type' : null,
                Schema::hasColumn('audit_logs', 'auditable_id') ? 'auditable_id' : null,
                Schema::hasColumn('audit_logs', 'old_values') ? 'old_values' : null,
                Schema::hasColumn('audit_logs', 'new_values') ? 'new_values' : null,
            ]);

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};