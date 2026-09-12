<?php

namespace Modules\Auth\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\Http\Requests\UpdatePasswordRequest;
use Modules\Auth\Notifications\PasswordChangedNotification;
use Saucebase\Core\Helpers\Toast;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(UpdatePasswordRequest $request): RedirectResponse
    {
        $user = Auth::user();

        $user->update([
            'password' => Hash::make($request->validated()['password']),
        ]);

        $user->notify(new PasswordChangedNotification);

        Toast::success('Password changed successfully.');

        return back();
    }
}
