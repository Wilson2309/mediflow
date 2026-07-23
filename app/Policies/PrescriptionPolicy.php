<?php

namespace App\Policies;

use App\Models\Prescription;
use App\Models\User;
use Illuminate\Auth\Access\Response;

final class PrescriptionPolicy
{
    public function view(User $user, Prescription $prescription): Response
    {
        if ($denial = $this->contextDenial($user, $prescription)) {
            return $denial;
        }

        return $user->can('prescriptions.view')
            ? Response::allow()
            : Response::deny('missing_permission');
    }

    public function send(User $user, Prescription $prescription): Response
    {
        if ($denial = $this->contextDenial($user, $prescription)) {
            return $denial;
        }

        return $user->can('prescriptions.update')
            ? Response::allow()
            : Response::deny('missing_permission');
    }

    public function sign(User $user, Prescription $prescription): Response
    {
        if ($denial = $this->contextDenial($user, $prescription)) {
            return $denial;
        }

        if (! $user->can('prescriptions.sign')) {
            return Response::deny('missing_permission');
        }

        if ($denial = $this->ownerDenial($user, $prescription)) {
            return $denial;
        }

        return $this->mutableDenial($prescription) ?? Response::allow();
    }

    public function update(User $user, Prescription $prescription): Response
    {
        if ($denial = $this->contextDenial($user, $prescription)) {
            return $denial;
        }

        if (! $user->can('prescriptions.update')) {
            return Response::deny('missing_permission');
        }

        if ($denial = $this->ownerDenial($user, $prescription)) {
            return $denial;
        }

        return $this->mutableDenial($prescription) ?? Response::allow();
    }

    public function delete(User $user, Prescription $prescription): Response
    {
        if ($denial = $this->contextDenial($user, $prescription)) {
            return $denial;
        }

        if (! $user->can('prescriptions.delete')) {
            return Response::deny('missing_permission');
        }

        if ($prescription->hasSignatureArtifacts()) {
            return Response::deny('already_signed');
        }

        return Response::allow();
    }

    private function contextDenial(User $user, Prescription $prescription): ?Response
    {
        $clinicId = (int) ($user->current_clinic_id ?? 0);
        $prescription->loadMissing(['patient', 'doctor']);

        if ($clinicId === 0) {
            return Response::deny('wrong_clinic');
        }

        if ((int) $prescription->patient?->clinic_id !== $clinicId
            || (int) $prescription->doctor?->clinic_id !== $clinicId) {
            return Response::deny('wrong_clinic');
        }

        if ($user->status !== 'active') {
            return Response::deny('inactive_user');
        }

        if (! $user->clinics()
            ->where('clinics.id', $clinicId)
            ->exists()) {
            return Response::deny('inactive_membership');
        }

        if (! $user->clinics()
            ->where('clinics.id', $clinicId)
            ->where('clinics.status', 'active')
            ->exists()) {
            return Response::deny('inactive_clinic');
        }

        return null;
    }

    private function ownerDenial(User $user, Prescription $prescription): ?Response
    {
        if ($user->hasAnyRole(['administrador', 'super_admin']) || ! $user->hasRole('medico')) {
            return Response::deny('not_owner');
        }

        if ($prescription->doctor?->status !== 'active') {
            return Response::deny('inactive_doctor');
        }

        if ((int) $prescription->doctor?->user_id !== (int) $user->id) {
            return Response::deny('not_owner');
        }

        return null;
    }

    private function mutableDenial(Prescription $prescription): ?Response
    {
        if ($prescription->status !== 'active') {
            return Response::deny($prescription->status === 'cancelled' ? 'cancelled' : 'invalid_status');
        }

        if ($prescription->hasSignatureArtifacts()) {
            return Response::deny('already_signed');
        }

        return null;
    }
}
