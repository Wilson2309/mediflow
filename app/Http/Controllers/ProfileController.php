<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
    public function destroy(Request $request): RedirectResponse
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

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
