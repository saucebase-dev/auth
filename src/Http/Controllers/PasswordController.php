<?php

namespace Modules\Auth\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\Http\Requests\UpdatePasswordRequest;
use Modules\Auth\Notifications\PasswordChangedNotification;
use Saucebase\Core\Toast;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(UpdatePasswordRequest $request): RedirectResponse
    {
        $user = Auth::user();

        $password = $request->validated()['password'];
        $user->update(['password' => Hash::make($password)]);

        // Re-issues this device's remember-me cookie with the new hash (it checks the password
        // it is given against the stored one, so it comes after the save): only the other
        // devices keep the old fingerprint, and `AuthenticateSession` ends them.
        Auth::logoutOtherDevices($password);
        $request->session()->regenerate();

        $user->notify(new PasswordChangedNotification);

        Toast::success(__('Password changed successfully.'));

        return back();
    }
}
