<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use App\Services\ProfilePhotoService;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        $candidate = $request->user()->loadMissing('candidate')->candidate;
        if ($candidate) {
            app(ProfilePhotoService::class)->importDefaultFromDidit($candidate);
            $request->user()->refresh();
        }
        $identityData = $candidate?->identity_verification_data ?? [];

        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
            'identityVerification' => [
                'verified' => $candidate?->identity_verification_status === 'APPROVED',
                'provider' => $identityData['provider'] ?? null,
            ],
            'profilePhoto' => [
                'url' => $request->user()->profile_photo_path ? route('profile.photo', ['v' => $request->user()->updated_at?->timestamp]) : null,
                'source' => $request->user()->profile_photo_source,
            ],
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();
        $user->fill(collect($validated)->except('profile_photo')->all());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($request->hasFile('profile_photo')) {
            app(ProfilePhotoService::class)->storeUpload($user, $request->file('profile_photo'));
        }

        return Redirect::route('profile.edit');
    }

    public function photo(Request $request)
    {
        $user = $request->user();
        abort_unless($user->profile_photo_path && Storage::disk('local')->exists($user->profile_photo_path), 404);

        return response()->file(Storage::disk('local')->path($user->profile_photo_path), [
            'Content-Disposition' => 'inline; filename="foto-perfil"',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
