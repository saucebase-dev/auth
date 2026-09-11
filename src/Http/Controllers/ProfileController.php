<?php

namespace Modules\Auth\Http\Controllers;

use App\Helpers\Toast;
use App\Settings\SettingsSection;
use Illuminate\Http\RedirectResponse;
use Modules\Auth\Http\Requests\UpdateProfileAvatarRequest;
use Modules\Auth\Http\Requests\UpdateProfileInfoRequest;

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
        auth()->user()->update($request->validated());

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
