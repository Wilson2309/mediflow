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
        $this->ensureReferencesAreValid();
        $this->replaceForeignKey('RESTRICT');
    }

    public function down(): void
    {
        $this->ensureSchemaIsAvailable();
        $this->ensureReferencesAreValid();
        $this->replaceForeignKey('SET NULL');
    }

    private function replaceForeignKey(string $onDelete): void
    {
        if (! in_array($onDelete, ['RESTRICT', 'SET NULL'], true)) {
            throw new RuntimeException('Unsupported prescription consultation delete action.');
        }

        $foreignKey = $this->consultationForeignKey();
        $currentAction = strtoupper(trim((string) ($foreignKey['on_delete'] ?? '')));

        if ($currentAction === $onDelete) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $existingConstraint = $this->validatedConstraintName($foreignKey);
            $targetConstraint = $this->targetConstraintName($onDelete);
            $supportingIndex = $this->validatedConsultationIndexName();

            DB::statement(
                'ALTER TABLE `prescriptions` '
                .'DROP FOREIGN KEY `'.$existingConstraint.'`, '
                .'DROP INDEX `'.$supportingIndex.'`, '
                .'ADD INDEX `'.$supportingIndex.'` (`consultation_id`), '
                .'ADD CONSTRAINT `'.$targetConstraint.'` '
                .'FOREIGN KEY (`consultation_id`) '
                .'REFERENCES `consultations` (`id`) '
                .'ON DELETE '.$onDelete,
            );

            return;
        }

        if ($driver === 'pgsql') {
            $existingConstraint = $this->validatedConstraintName($foreignKey);
            $targetConstraint = $this->targetConstraintName($onDelete);

            DB::statement(
                'ALTER TABLE "prescriptions" '
                .'DROP CONSTRAINT "'.$existingConstraint.'", '
                .'ADD CONSTRAINT "'.$targetConstraint.'" '
                .'FOREIGN KEY ("consultation_id") REFERENCES "consultations" ("id") '
                .'ON DELETE '.$onDelete,
            );

            return;
        }

        if ($driver === 'sqlite') {
            Schema::table('prescriptions', function (Blueprint $table) use ($onDelete): void {
                $table->dropForeign(['consultation_id']);
                $foreign = $table->foreign('consultation_id')
                    ->references('id')
                    ->on('consultations');

                $onDelete === 'RESTRICT'
                    ? $foreign->restrictOnDelete()
                    : $foreign->nullOnDelete();
            });

            return;
        }

        throw new RuntimeException('Unsupported database driver for prescription consultation foreign key.');
    }

    /** @return array<string, mixed> */
    private function consultationForeignKey(): array
    {
        $matches = array_values(array_filter(
            Schema::getForeignKeys('prescriptions'),
            static fn (array $foreignKey): bool => array_map(
                'strtolower',
                $foreignKey['columns'] ?? [],
            ) === ['consultation_id'],
        ));

        if (count($matches) !== 1) {
            throw new RuntimeException('Prescription consultation foreign key is missing or ambiguous.');
        }

        $foreignKey = $matches[0];
        $foreignTable = strtolower((string) ($foreignKey['foreign_table'] ?? ''));
        $foreignColumns = array_map('strtolower', $foreignKey['foreign_columns'] ?? []);

        if ($foreignTable !== 'consultations' || $foreignColumns !== ['id']) {
            throw new RuntimeException('Prescription consultation foreign key has an unexpected target.');
        }

        return $foreignKey;
    }

    /** @param array<string, mixed> $foreignKey */
    private function validatedConstraintName(array $foreignKey): string
    {
        $name = $foreignKey['name'] ?? null;

        if (! is_string($name) || preg_match('/\A[A-Za-z0-9_]+\z/D', $name) !== 1) {
            throw new RuntimeException('Prescription consultation foreign key name is invalid.');
        }

        return $name;
    }

    private function validatedConsultationIndexName(): string
    {
        $matches = array_values(array_filter(
            Schema::getIndexes('prescriptions'),
            static fn (array $index): bool => array_map(
                'strtolower',
                $index['columns'] ?? [],
            ) === ['consultation_id']
                && ! ($index['unique'] ?? false)
                && ! ($index['primary'] ?? false),
        ));

        if (count($matches) !== 1) {
            throw new RuntimeException('Prescription consultation supporting index is missing or ambiguous.');
        }

        $name = $matches[0]['name'] ?? null;

        if (! is_string($name) || preg_match('/\A[A-Za-z0-9_]+\z/D', $name) !== 1) {
            throw new RuntimeException('Prescription consultation supporting index name is invalid.');
        }

        return $name;
    }

    private function targetConstraintName(string $onDelete): string
    {
        return $onDelete === 'RESTRICT'
            ? 'prescriptions_consultation_id_restrict_foreign'
            : 'prescriptions_consultation_id_foreign';
    }

    private function ensureSchemaIsAvailable(): void
    {
        if (! Schema::hasTable('consultations')
            || ! Schema::hasTable('prescriptions')
            || ! Schema::hasColumn('prescriptions', 'consultation_id')) {
            throw new RuntimeException('Prescription consultation schema is unavailable.');
        }

        $consultationColumn = collect(Schema::getColumns('prescriptions'))
            ->first(fn (array $column): bool => strtolower((string) ($column['name'] ?? '')) === 'consultation_id');

        if (! $consultationColumn || ! ($consultationColumn['nullable'] ?? false)) {
            throw new RuntimeException('Prescription consultation column must remain nullable.');
        }

        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->validatedConsultationIndexName();
        }

        $this->consultationForeignKey();
    }

    private function ensureReferencesAreValid(): void
    {
        $hasInvalidReference = DB::table('prescriptions as prescriptions_to_check')
            ->whereNotNull('prescriptions_to_check.consultation_id')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('consultations as consultations_to_check')
                    ->whereColumn(
                        'consultations_to_check.id',
                        'prescriptions_to_check.consultation_id',
                    );
            })
            ->exists();

        if ($hasInvalidReference) {
            throw new RuntimeException('Invalid prescription consultation references must be reviewed before changing the foreign key.');
        }
    }
};
