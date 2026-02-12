<?php

namespace Modules\Auth\Providers;

use App\Models\User;
use App\Providers\ModuleServiceProvider;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Lab404\Impersonate\Services\ImpersonateManager;
use Modules\Auth\Models\SocialAccount;

class AuthServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Auth';

    protected string $nameLower = 'auth';

    protected array $providers = [
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();

        /**
         * Establishes a dynamic relationship mapping between the User model and the SocialAccount model
         * specifically for social authentication operations within the Auth module.
         *
         * This relationship is defined here to maintain separation of concerns and keep
         * authentication-related logic contained within the Auth service provider. However,
         * if you prefer a more traditional approach or need the relationship to be
         * available globally throughout your application, consider moving this
         * relationship definition directly to the User model class or using the
         * UseSocialite trait (modules/Auth/app/Traits/UseSocialite.php) in your User model
         * for a more modular approach.
         *
         * @see User - The source model in the relationship
         * @see SocialAccount - The target model containing social authentication information
         * @see \Modules\Auth\Traits\UseSocialite - Trait providing social authentication relationships
         */
        User::resolveRelationUsing('socialAccounts', function (User $user) {
            return $user->hasMany(SocialAccount::class);
        });
    }

    /**
     * Share Inertia data globally.
     */
    protected function shareInertiaData(): void
    {
        Inertia::share('auth.user', fn () => Auth::user());

        Inertia::share('impersonation', fn () => $this->isUserImpersonated() ? [
            'user' => [
                ...Auth::user()->only(['id', 'name', 'email', 'avatar']),
                'role' => Auth::user()->roles->first()?->name,
            ],
            'route' => route('filament-impersonate.leave'),
            'label' => __('Stop Impersonation'),
            'recent' => $this->getRecentImpersonationHistory(),
        ] : null);
    }

    protected function isUserImpersonated(): bool
    {
        if (! Auth::user()) {
            return false;
        }

        $impersonate = app(ImpersonateManager::class);
        $impersonatorGuard = $impersonate->getImpersonatorGuardUsingName();
        $currentPanelGuard = \Filament\Facades\Filament::getAuthGuard();
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

        $currentUserId = Auth::user()->id;

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
                $user = $users->get($id);
                $orderedUsers[] = [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                    'role' => $user->roles->first()?->name,
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

        $this->mergeConfigFrom(module_path($this->name, 'config/services.php'), 'services');
    }
}
