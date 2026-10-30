<?php

namespace Modules\Auth\Providers;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Laravel\Passkeys\Passkeys;
use Modules\Auth\Policies\UserPolicy;
use Modules\Auth\Services\SocialiteService;
use Modules\Auth\Settings\AuthSettings;
use Saucebase\Core\Providers\ModuleServiceProvider;
use Spatie\Permission\Models\Role;
use STS\FilamentImpersonate\Actions\Impersonate;
use STS\FilamentImpersonate\ImpersonateManager;

class AuthServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        parent::register();

        // The module registers its own passkey routes (routes/web.php), behind the
        // setting and through the same sign-in as every other method. Set during
        // register so it lands before the package's provider boots.
        Passkeys::ignoreRoutes();
    }

    public function boot(): void
    {
        parent::boot();

        Gate::policy(User::class, UserPolicy::class);

        // `throttle:passkeys`: its own counter, so a sign-in (two requests) does not
        // share the per-address budget of password and magic-link sign-in. Keyed by
        // user, falling back to the address for guests.
        RateLimiter::for('passkeys', fn (Request $request): Limit => Limit::perMinute(10)
            ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));

        // Every impersonate button asks the same question the controllers do.
        Impersonate::configureUsing(fn (Impersonate $action) => $action
            ->authorize(fn (User $record): bool => Gate::allows('impersonate', $record)));
    }

    /**
     * Share Inertia data globally.
     */
    protected function shareInertiaData(): void
    {
        Inertia::share([
            'auth.user' => fn () => Auth::user(),
            'auth.last_social_provider' => fn () => request()->cookie('last_social_provider'),
            'auth.socialite_providers' => fn (): array => $this->app->make(SocialiteService::class)->enabledProviders(),
            'auth.registration_enabled' => fn (): bool => $this->app->make(AuthSettings::class)->registration_enabled,
            'auth.modal_enabled' => fn (): bool => $this->app->make(AuthSettings::class)->modal_enabled,
            'auth.magic_link_enabled' => fn (): bool => $this->app->make(AuthSettings::class)->magic_link_enabled,
            'auth.passkeys_enabled' => fn (): bool => $this->app->make(AuthSettings::class)->passkeys_enabled,
        ]);

        Inertia::share('impersonation', function () {
            if (! $this->isUserImpersonated()) {
                return null;
            }

            /** @var User $user */
            $user = Auth::user();

            /** @var Role|null $role */
            $role = $user->roles->first();

            return [
                'user' => [
                    ...$user->only(['id', 'name', 'email', 'avatar']),
                    'role' => $role?->name,
                ],
                'route' => route('filament-impersonate.leave'),
                'label' => __('Stop Impersonation'),
                'recent' => $this->getRecentImpersonationHistory(),
            ];
        });
    }

    protected function isUserImpersonated(): bool
    {
        if (! Auth::user()) {
            return false;
        }

        $impersonate = app(ImpersonateManager::class);
        $impersonatorGuard = $impersonate->getImpersonatorGuardUsingName();
        $currentPanelGuard = Filament::getAuthGuard();
        $isImpersonating = $impersonate->isImpersonating();

        return $isImpersonating
            && $currentPanelGuard
            && $impersonatorGuard === $currentPanelGuard;
    }

    /**
     * Get recent impersonation history with user data.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function getRecentImpersonationHistory(): array
    {
        $historyIds = session()->get('impersonation.recent_history', []);

        if (empty($historyIds)) {
            return [];
        }

        /** @var User $currentUser */
        $currentUser = Auth::user();
        $currentUserId = $currentUser->id;

        // Fetch users (filters deleted users automatically)
        $users = User::with('roles:name')
            ->whereIn('id', $historyIds)
            ->where('id', '!=', $currentUserId)
            ->get(['id', 'name', 'email', 'avatar'])
            ->keyBy('id');

        // Maintain original chronological order
        $orderedUsers = [];
        foreach ($historyIds as $id) {
            if ($users->has($id) && $id !== $currentUserId) {
                /** @var User $user */
                $user = $users->get($id);
                /** @var Role|null $userRole */
                $userRole = $user->roles->first();
                $orderedUsers[] = [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                    'role' => $userRole?->name,
                ];

                // Limit to 3 users
                if (count($orderedUsers) >= 3) {
                    break;
                }
            }
        }

        return $orderedUsers;
    }

    /**
     * Register config.
     */
    protected function registerConfig(): void
    {
        parent::registerConfig();

        $this->mergeConfigFrom(module_path($this->moduleName(), 'config/services.php'), 'services');
    }
}
