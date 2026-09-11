<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used during authentication for various
    | messages that we need to display to the user. You are free to modify
    | these language lines according to your application's requirements.
    |
    */
    'welcome' => '👋 Welcome aboard, :name!',
    'welcome-back' => '👋 Welcome back, :name!',
    'failed' => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',
    'verification-link-sent' => 'A fresh verification link has been sent to your email address.',
    'magic-link-sent' => "If an account with that email exists, we've sent a magic login link.",
    'magic-link-expired' => 'This magic link has expired or has already been used.',
    'registration' => [
        'title' => 'Registration',
        'description' => 'Control whether visitors can create new accounts.',
        'enabled' => 'Allow new registrations',
        'help' => 'When disabled, the sign-up page returns 404 and social login cannot create new accounts.',
    ],
    'modal' => [
        'enabled' => 'Open sign-in and sign-up in a modal',
        'help' => 'Links on the site open sign-in and registration over the current page. Their URLs still work on their own, so a typed, bookmarked or shared link lands on the full page.',
    ],
    'notifications' => [
        'title' => 'Notifications',
        'description' => 'Configure security notifications sent to users.',
        'login-enabled' => 'Send login notifications',
        'login-help' => 'Email users after a successful sign-in to their account.',
    ],
    'login-notification' => [
        'subject' => 'New sign-in to your :app account',
        'greeting' => 'Hello :name,',
        'notice' => 'We noticed a new sign-in to your :app account.',
        'app' => 'App: :app',
        'time' => 'Time: :time',
        'ip-address' => 'IP address: :ip',
        'device-details' => 'Device details: :device',
        'recognized' => 'If this was you, no action is needed.',
        'action' => 'Reset your password',
        'unrecognized' => "If you don't recognize this activity, reset your password immediately.",
        'unknown' => 'Unknown',
    ],
];
