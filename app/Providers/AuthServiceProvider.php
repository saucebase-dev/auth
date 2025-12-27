<?php

namespace Modules\Auth\Providers;

use App\Facades\Navigation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Spatie\Navigation\Section;

class AuthServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Auth';

    protected string $moduleNameLower = 'auth';

    /**
     * Boot the application events.
     */
    public function boot(): void
    {
        $this->registerCommands();
        $this->registerCommandSchedules();
        $this->registerTranslations();
        $this->registerConfig();
        $this->loadMigrationsFrom(module_path($this->moduleName, 'database/migrations'));
        $this->shareInertiaData();

        // Register navigation after routes are loaded
        $this->app->booted(function () {
            $this->registerNavigation();
        });
    }

    /**
     * Register navigation items.
     */
    protected function registerNavigation(): void
    {
        // User menu - Logout
        Navigation::add('Log out', '#', function (Section $section) {
            $section->attributes([
                'group' => 'user',
                'label' => 'Log out',
                'action' => 'logout',
                'icon' => 'lucide:log-out',
                'order' => 100,
            ]);
        });
    }

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }

    /**
     * Share Inertia data globally.
     */
    protected function shareInertiaData(): void
    {
        Inertia::share('auth.user', fn () => Auth::user());
        Inertia::share('canLogin', true);
        Inertia::share('canRegister', true);
    }

    /**
     * Register commands in the format of Command::class
     */
    protected function registerCommands(): void
    {
        // $this->commands([]);
    }

    /**
     * Register command Schedules.
     */
    protected function registerCommandSchedules(): void
    {
        // $this->app->booted(function () {
        //     $schedule = $this->app->make(Schedule::class);
        //     $schedule->command('inspire')->hourly();
        // });
    }

    /**
     * Register translations.
     */
    public function registerTranslations(): void
    {
        $this->loadTranslationsFrom(module_path($this->moduleName, 'lang'));
        $this->loadJsonTranslationsFrom(module_path($this->moduleName, 'lang'));
    }

    /**
     * Register config.
     */
    protected function registerConfig(): void
    {
        $this->mergeConfigFrom(module_path($this->moduleName, 'config/services.php'), 'services');
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [];
    }
}
