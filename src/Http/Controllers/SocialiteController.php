<?php

namespace Modules\Auth\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User;
use Modules\Auth\Events\ReturningUserAuthenticated;
use Modules\Auth\Exceptions\SocialiteException;
use Modules\Auth\Services\SocialiteService;
use Saucebase\Core\Helpers\Toast;
use Symfony\Component\HttpFoundation\Response as RedirectResponse;

class SocialiteController extends Controller
{
    private SocialiteService $socialiteService;

    public function __construct(SocialiteService $socialiteService)
    {
        $this->socialiteService = $socialiteService;
    }

    public function redirect(string $provider): RedirectResponse
    {
        return Socialite::driver($provider)->redirect();
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        $validator = Validator::make(['provider' => $provider], [
            'provider' => 'required|string',
        ]);

        if ($validator->fails()) {
            return back()->with('error', trans('auth::socialite.error'));
        }

        // Check if user is already authenticated (account linking flow)
        if (Auth::check()) {
            try {
                /** @var User $socialUser */
                $socialUser = Socialite::driver($provider)->user();
                $this->socialiteService->linkAccountToUser(Auth::user(), $provider, $socialUser);
                Toast::success(trans('auth::socialite.account_connected', ['provider' => ucfirst($provider)]));
            } catch (SocialiteException $e) {
                Toast::error($e->getMessage());
            } catch (\Exception $e) {
                Toast::error(trans('auth::socialite.error'));
                report($e);
            } finally {
                return back();
            }
        }

        // Guest user - login/registration flow
        try {
            $user = $this->socialiteService->handleCallback($provider);
        } catch (SocialiteException $e) {
            Toast::error($e->getMessage());

            return redirect()->route('login');
        }

        Auth::login($user);

        $request->session()->regenerate();

        if (! $user->wasRecentlyCreated) {
            ReturningUserAuthenticated::dispatch(
                $user,
                now(),
                $request->ip(),
                $request->userAgent(),
            );
        }

        Toast::default(
            __($user->wasRecentlyCreated ? 'auth::auth.welcome' : 'auth::auth.welcome-back', [
                'name' => $user->name,
            ]),
        );

        return redirect()->intended(route('dashboard'))
            ->withCookie(cookie('last_social_provider', $provider, 60 * 24 * 365));
    }

    /**
     * Disconnect a social provider from user account
     */
    public function disconnect(string $provider): RedirectResponse
    {
        $user = Auth::user();

        try {
            $this->socialiteService->disconnectProvider($user, $provider);

            Toast::success(trans('auth::socialite.account_disconnected', ['provider' => $provider]));
        } catch (SocialiteException $e) {
            Toast::error($e->getMessage());
        }

        return back();
    }
}
