<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_service', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained('doctors')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['doctor_id', 'service_id']);
            $table->index('service_id');
        });

        $now = now();
        $pairs = DB::table('doctors')
            ->join('services', 'services.clinic_id', '=', 'doctors.clinic_id')
            ->where('doctors.status', 'active')
            ->where('services.status', 'active')
            ->select('doctors.id as doctor_id', 'services.id as service_id')
            ->get()
            ->map(fn ($row) => [
                'doctor_id' => $row->doctor_id,
                'service_id' => $row->service_id,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        if ($pairs !== []) {
            DB::table('doctor_service')->insertOrIgnore($pairs);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_service');
    }
};
