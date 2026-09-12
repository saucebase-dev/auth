<?php

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\EmailVerificationNotificationController;
use Modules\Auth\Http\Controllers\EmailVerificationPromptController;
use Modules\Auth\Http\Controllers\ForgotPasswordController;
use Modules\Auth\Http\Controllers\LoginController;
use Modules\Auth\Http\Controllers\MagicLinkController;
use Modules\Auth\Http\Controllers\PasswordController;
use Modules\Auth\Http\Controllers\ProfileController;
use Modules\Auth\Http\Controllers\RegisterController;
use Modules\Auth\Http\Controllers\ReimpersonateController;
use Modules\Auth\Http\Controllers\ResetPasswordController;
use Modules\Auth\Http\Controllers\SocialiteController;
use Modules\Auth\Http\Controllers\VerifyEmailController;
use Modules\Auth\Http\Middleware\EnsureRegistrationEnabled;
use Modules\Auth\Http\Middleware\EnsureSocialiteProviderEnabled;

Route::middleware('web')->group(function (): void {
    Route::prefix('auth')->group(function (): void {
        Route::middleware('guest')->group(function (): void {

            Route::get('login', [LoginController::class, 'create'])
                ->name('login');

            Route::post('login', [LoginController::class, 'store']);

            Route::middleware(EnsureRegistrationEnabled::class)->group(function (): void {
                Route::get('register', [RegisterController::class, 'create'])
                    ->name('register');

                Route::post('register', [RegisterController::class, 'store']);
            });

            Route::get('forgot-password', [ForgotPasswordController::class, 'create'])
                ->name('password.request');

            // Throttled like the reset itself: without it this is a button that
            // mails anybody on demand. The response stays generic either way, so
            // the limit does not become an account oracle.
            Route::post('forgot-password', [ForgotPasswordController::class, 'store'])
                ->middleware('throttle:6,1')
                ->name('password.email');

            Route::get('reset-password/{token}', [ResetPasswordController::class, 'create'])
                ->name('password.reset');

            Route::post('reset-password', [ResetPasswordController::class, 'store'])
                ->middleware('throttle:6,1')
                ->name('password.store');

            Route::get('magic-link', [MagicLinkController::class, 'create'])
                ->name('magic-link.create');

            Route::post('magic-link', [MagicLinkController::class, 'store'])
                ->middleware('throttle:5,1')
                ->name('magic-link.store');
        });

        Route::middleware('auth')->group(function (): void {

            Route::post('logout', [LoginController::class, 'destroy'])
                ->name('logout');

            Route::get('verify-email', EmailVerificationPromptController::class)
                ->name('verification.notice');

            Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
                ->middleware(['signed', 'throttle:6,1'])
                ->name('verification.verify');

            Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
                ->middleware('throttle:6,1')
                ->name('verification.send');

            Route::put('password', [PasswordController::class, 'update'])->name('password.update');

            Route::delete('socialite/{provider}', [SocialiteController::class, 'disconnect'])
                ->name('auth.socialite.disconnect');

            Route::post('impersonate/{userId}', ReimpersonateController::class)
                ->name('auth.impersonate.reimpersonate');
        });

        /**
         * Socialite Routes
         *
         * These routes are placed outside the auth/guest middleware groups because:
         * - Guests can use them for social login/registration
         * - Authenticated users can use them to connect additional social providers
         */
        Route::get('socialite/{provider}', [SocialiteController::class, 'redirect'])
            ->middleware(EnsureSocialiteProviderEnabled::class)
            ->name('auth.socialite.redirect');

        Route::get('socialite/{provider}/callback', [SocialiteController::class, 'callback'])
            ->middleware(EnsureSocialiteProviderEnabled::class)
            ->name('auth.socialite.callback');

        /**
         * Magic Link Authentication
         *
         * Placed outside guest/auth groups because the user clicking the link
         * is not yet authenticated (they are in their email client).
         */
        Route::get('magic-link/{token}', [MagicLinkController::class, 'authenticate'])
            ->middleware('throttle:10,1')
            ->name('magic-link.authenticate');
    });

    /**
     * Account Settings Routes
     *
     * Profile management lives under the /settings prefix rather than /auth so the
     * URLs and route names stay stable for the settings sidebar, the user menu, and
     * PasswordChangedNotification, all of which resolve `settings.profile`.
     */
    Route::group(['middleware' => [
        'auth',
        'verified',
        'role:admin|user',
    ]], function (): void {
        Route::prefix('settings')->group(function (): void {
            /*
             * Profile is a section of the settings modal, which lives behind the
             * `#settings/profile` fragment. This route stays so the links that
             * resolve `settings.profile` by name — the user menu and
             * PasswordChangedNotification — keep working: it drops the visitor on
             * the dashboard with that section already open.
             */
            Route::get('profile', [ProfileController::class, 'show'])
                ->name('settings.profile');

            Route::patch('profile/info', [ProfileController::class, 'updateInfo'])
                ->name('settings.profile.update-info');

            Route::post('profile/avatar', [ProfileController::class, 'updateAvatar'])
                ->name('settings.profile.update-avatar');

            Route::delete('profile/avatar', [ProfileController::class, 'deleteAvatar'])
                ->name('settings.profile.delete-avatar');

            Route::put('profile/password', [PasswordController::class, 'update'])
                ->name('settings.profile.password.update');
        });
    });
});
