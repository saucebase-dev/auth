<?php

namespace Modules\Auth\Filament;

use App\Filament\ModulePlugin;
use Filament\Contracts\Plugin;
use Filament\Panel;

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

    public function boot(Panel $panel): void
    {
        //
    }
}
