<?php

namespace Modules\Auth\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Uri;
use Saucebase\Core\Facades\Home;
use Saucebase\Core\Helpers\Toast;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        /** @var MustVerifyEmail&User $user */
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended($this->home($request));
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));

            Toast::success(__('Email verified'), __('Your email address has been confirmed.'));
        }

        return redirect()->intended($this->home($request));
    }

    /** Home, flagged so the page can say the address is confirmed. */
    private function home(EmailVerificationRequest $request): string
    {
        return (string) Uri::of(Home::url($request, Home::VERIFIED))->withQuery(['verified' => 1]);
    }
}
