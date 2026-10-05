<?php

namespace Modules\Auth\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Saucebase\Core\Facades\Home;

class EmailVerificationNotificationController extends Controller
{
    /**
     * Send a new email verification notification.
     */
    public function store(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(Home::url($request));
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', trans('auth::auth.verification-link-sent'));
    }
}
