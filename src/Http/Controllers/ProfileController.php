<?php

namespace Modules\Auth\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Modules\Auth\Http\Requests\UpdateProfileAvatarRequest;
use Modules\Auth\Http\Requests\UpdateProfileInfoRequest;
use Saucebase\Core\Helpers\Toast;
use Saucebase\Core\Settings\SettingsSection;

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

        $user->update($request->validated());

        // A new address has not been confirmed, whatever the old one had earned.
        if ($user->wasChanged('email')) {
            $user->forceFill(['email_verified_at' => null])->save();
        }

        Toast::success('Profile updated successfully!');

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

        Toast::success('Avatar updated successfully.');

        return back();
    }

    /**
     * Delete the user's avatar.
     */
    public function deleteAvatar(): RedirectResponse
    {
        auth()->user()->clearMediaCollection('avatars');

        Toast::success('Avatar removed successfully.');

        return back();
    }
}
