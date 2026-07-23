<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensureSchemaIsAvailable();

        $hasLostAttribution = DB::table('prescriptions')
            ->whereNull('signed_by_user_id')
            ->where(function ($query): void {
                $query->whereNotNull('signed_at')
                    ->orWhereNotNull('signature_verification_code')
                    ->orWhereNotNull('signature_hash')
                    ->orWhereNotNull('signed_ip_address')
                    ->orWhereNotNull('signed_user_agent');
            })
            ->exists();

        if ($hasLostAttribution) {
            throw new RuntimeException('Existing signature attribution must be reviewed before changing the foreign key.');
        }

        $this->replaceForeignKey('RESTRICT');
    }

    public function down(): void
    {
        $this->ensureSchemaIsAvailable();

        $this->replaceForeignKey('SET NULL');
    }

    private function replaceForeignKey(string $onDelete): void
    {
        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement(
                'ALTER TABLE `prescriptions` '
                .'DROP FOREIGN KEY `prescriptions_signed_by_user_id_foreign`, '
                .'ADD CONSTRAINT `prescriptions_signed_by_user_id_foreign` '
                .'FOREIGN KEY (`signed_by_user_id`) REFERENCES `users` (`id`) '
                .'ON DELETE '.$onDelete,
            );

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement(
                'ALTER TABLE "prescriptions" '
                .'DROP CONSTRAINT "prescriptions_signed_by_user_id_foreign", '
                .'ADD CONSTRAINT "prescriptions_signed_by_user_id_foreign" '
                .'FOREIGN KEY ("signed_by_user_id") REFERENCES "users" ("id") '
                .'ON DELETE '.$onDelete,
            );

            return;
        }

        Schema::table('prescriptions', function (Blueprint $table): void {
            $table->dropForeign(['signed_by_user_id']);
        });

        Schema::table('prescriptions', function (Blueprint $table) use ($onDelete): void {
            $foreign = $table->foreign('signed_by_user_id')
                ->references('id')
                ->on('users');

            $onDelete === 'RESTRICT'
                ? $foreign->restrictOnDelete()
                : $foreign->nullOnDelete();
        });
    }

    private function ensureSchemaIsAvailable(): void
    {
        if (! Schema::hasTable('users')
            || ! Schema::hasTable('prescriptions')
            || ! Schema::hasColumn('prescriptions', 'signed_by_user_id')) {
            throw new RuntimeException('Prescription signature schema is unavailable.');
        }
    }
};
