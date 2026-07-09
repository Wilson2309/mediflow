<?php

namespace Database\Seeders;

use App\Models\Clinic;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminClinicSeeder extends Seeder
{
    public function run(): void
    {
        $clinic = Clinic::where('ruc', '0999999999001')->firstOrFail();
        $admin = User::where('email', 'admin@mediflow.com')->first();

        if (! $admin) {
            return;
        }

        $admin->forceFill([
            'clinic_id' => $clinic->id,
            'current_clinic_id' => $clinic->id,
        ])->save();

        $admin->clinics()->syncWithoutDetaching([$clinic->id]);
    }
}
