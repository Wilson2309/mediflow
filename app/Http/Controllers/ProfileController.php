<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Prescription;
use App\Models\User;
use App\Support\ControlledResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        if (strtolower($request->user()->email) === 'admin@mediflow.com'
            && strtolower($request->validated('email')) !== 'admin@mediflow.com') {
            throw ValidationException::withMessages([
                'email' => 'El correo del administrador principal no puede cambiarse.',
            ]);
        }

        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse|JsonResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        if (strtolower($user->email) === 'admin@mediflow.com') {
            return Redirect::route('profile.edit')->withErrors([
                'password' => 'El administrador principal de MediFlow no puede eliminarse.',
            ], 'userDeletion');
        }

        $clinicId = $user->activeClinicId();

        if ($clinicId && $user->hasRole('administrador')) {
            $administratorCount = User::whereHas('clinics', fn ($query) => $query->where('clinics.id', $clinicId))
                ->whereHas('roles', fn ($query) => $query->where('name', 'administrador'))
                ->count();

            if ($administratorCount <= 1) {
                return Redirect::route('profile.edit')->withErrors([
                    'password' => 'El último administrador de la clínica no puede eliminarse.',
                ], 'userDeletion');
            }
        }

        $outcome = DB::transaction(function () use ($user): string {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $hasSignatureAttribution = Prescription::query()
                ->where('signed_by_user_id', $lockedUser->id)
                ->lockForUpdate()
                ->first(['id']) !== null;

            if ($hasSignatureAttribution) {
                return 'blocked';
            }

            $lockedUser->delete();

            return 'deleted';
        });

        if ($outcome === 'blocked') {
            if ($request->expectsJson()) {
                return ControlledResponse::jsonError(409, 'USER_REQUIRES_DEACTIVATION');
            }

            return Redirect::route('profile.edit')->withErrors([
                'password' => 'La cuenta no puede eliminarse. Debe inactivarse para conservar la trazabilidad.',
            ], 'userDeletion');
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
