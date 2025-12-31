<?php

namespace Modules\Auth\Listeners;

use App\Events\NavigationRegistering;
use Spatie\Navigation\Facades\Navigation;
use Spatie\Navigation\Section;

class RegisterNavigation
{
    /**
     * Handle the event.
     */
    public function handle(NavigationRegistering $event): void
    {
        // User menu - Logout
        Navigation::add('Log out', '#', function (Section $section) {
            $section->attributes([
                'group' => 'user',
                'action' => 'logout',
                'slug' => 'logout',
                'order' => 100,
            ]);
        });
    }
}
