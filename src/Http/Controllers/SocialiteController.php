<?php

namespace Modules\Auth\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User;
use Modules\Auth\Exceptions\SocialiteException;
use Modules\Auth\Services\SocialiteService;
use Saucebase\Core\Facades\Home;
use Saucebase\Core\Helpers\Toast;
use Symfony\Component\HttpFoundation\Response as RedirectResponse;

class SocialiteController extends Controller
{
    public function __construct(private readonly SocialiteService $socialiteService) {}

    public function redirect(string $provider): RedirectResponse
    {
        return Socialite::driver($provider)->redirect();
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
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
            }

            return back();
        }

        // Guest user - login/registration flow
        try {
            $user = $this->socialiteService->handleCallback($provider);
        } catch (SocialiteException $e) {
            Toast::error($e->getMessage());

            return redirect()->route('login');
        }

        if ($user->wasRecentlyCreated) {
            Auth::login($user);
            $request->session()->regenerate();
            Toast::default(__('auth::auth.welcome', ['name' => $user->name]));
        } else {
            $this->signIn($request, $user);
        }

        return redirect()->intended(Home::url($request, $user->wasRecentlyCreated ? Home::REGISTERED : Home::LOGIN))
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
