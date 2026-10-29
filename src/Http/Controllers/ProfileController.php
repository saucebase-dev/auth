<?php

namespace Modules\Auth\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Modules\Auth\Http\Requests\UpdateProfileAvatarRequest;
use Modules\Auth\Http\Requests\UpdateProfileInfoRequest;
use Modules\Auth\Support\PasswordConfirmation;
use Saucebase\Core\Settings\SettingsSection;
use Saucebase\Core\Toast;

class ProfileController extends Controller
{
    /**
     * Send visitors to the profile section of the settings modal.
     *
     * The section itself lives behind the `#settings/profile` fragment, which
     * never reaches the server. Links that resolve this route by name — the user
     * menu and PasswordChangedNotification — land on the dashboard with the
     * section already open.
     */
    public function show(): RedirectResponse
    {
        return redirect()->to(SettingsSection::url('profile'));
    }

    /**
     * Update the user's profile information.
     */
    public function updateInfo(UpdateProfileInfoRequest $request): RedirectResponse
    {
        $user = auth()->user();

        // The email is where every security alert goes, so moving it needs the same
        // fresh proof as adding a passkey. The name does not.
        if ($request->validated('email') !== $user->email && ! PasswordConfirmation::isRecent()) {
            throw ValidationException::withMessages([
                'email' => __('Confirm it\'s you before changing your email.'),
                // The panel reopens its dialog on this one.
                'confirm_identity' => __('Confirm it\'s you to continue.'),
            ]);
        }

        $user->update($request->validated());

        // A new address has not been confirmed, whatever the old one had earned.
        if ($user->wasChanged('email')) {
            $user->forceFill(['email_verified_at' => null])->save();
        }

        Toast::success(__('Profile updated successfully!'));

        return back();
    }

    /**
     * Update the user's avatar.
     */
    public function updateAvatar(UpdateProfileAvatarRequest $request): RedirectResponse
    {
        $user = auth()->user();

        if ($request->hasFile('avatar')) {
            $user->clearMediaCollection('avatars');
            $user->addMediaFromRequest('avatar')
                ->toMediaCollection('avatars');
        }

        Toast::success(__('Avatar updated successfully.'));

        return back();
    }

    /**
     * Delete the user's avatar.
     */
    public function deleteAvatar(): RedirectResponse
    {
        auth()->user()->clearMediaCollection('avatars');

        Toast::success(__('Avatar removed successfully.'));

        return back();
    }
}
