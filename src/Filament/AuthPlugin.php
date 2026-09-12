<?php

namespace Modules\Auth\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Facades\FilamentView;
use Saucebase\Core\Filament\ModulePlugin;

class AuthPlugin implements Plugin
{
    use ModulePlugin;

    public function getModuleName(): string
    {
        return 'Auth';
    }

    public function getId(): string
    {
        return 'auth';
    }

    public static function getNavigationGroupSort(): int
    {
        return 2;
    }

    public function boot(Panel $panel): void
    {
        FilamentView::spaUrlExceptions([config('filament-impersonate.redirect_to', '/')]);
    }
}
