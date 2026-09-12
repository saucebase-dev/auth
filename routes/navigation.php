<?php

use Saucebase\Core\Facades\Navigation;
use Saucebase\Core\Navigation\Section;

/*
|--------------------------------------------------------------------------
| Auth Module Navigation
|--------------------------------------------------------------------------
|
| Define Auth module navigation items here.
| These items will be loaded automatically when the module is enabled.
|
*/

// User menu - Settings (opens the settings modal over the current page)
Navigation::add('Settings', '#settings', function (Section $section) {
    $section->attributes([
        'group' => 'user',
        'slug' => 'settings',
        'icon' => 'settings',
        'order' => 10,
        // Renders a plain anchor: a fragment must not trigger an Inertia visit.
        'external' => true,
    ]);
});

// User menu - Logout
Navigation::add('Log out', '#', function (Section $section) {
    $section->attributes([
        'group' => 'user',
        'action' => 'logout',
        'slug' => 'logout',
        'icon' => 'logout',
        'order' => 100,
    ]);
});
