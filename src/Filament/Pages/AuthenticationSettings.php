<?php

namespace Modules\Auth\Filament\Pages;

use BackedEnum;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\Rule;
use Modules\Auth\Services\SocialiteService;
use Modules\Auth\Settings\AuthSettings;
use Saucebase\Core\Filament\Pages\SettingsPage;

class AuthenticationSettings extends SettingsPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?int $navigationSort = 1;

    protected static string $settings = AuthSettings::class;

    public static function getNavigationLabel(): string
    {
        return __('Authentication');
    }

    public function getTitle(): string
    {
        return __('Authentication Settings');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('auth::auth.registration.title'))
                ->description(__('auth::auth.registration.description'))
                ->icon(Heroicon::OutlinedUserPlus)
                ->schema([
                    Toggle::make('registration_enabled')
                        ->label(__('auth::auth.registration.enabled'))
                        ->helperText(__('auth::auth.registration.help'))
                        ->extraAttributes(['data-testid' => 'admin-registration-enabled']),
                    Toggle::make('modal_enabled')
                        ->label(__('auth::auth.modal.enabled'))
                        ->helperText(__('auth::auth.modal.help'))
                        ->extraAttributes(['data-testid' => 'admin-modal-enabled']),
                ])
                ->columns(1),
            Section::make(__('Social Login'))
                ->description(__('Choose which social providers visitors can use to sign in or create an account.'))
                ->icon(Heroicon::OutlinedShare)
                ->schema([
                    CheckboxList::make('enabled_socialite_providers')
                        ->label(__('Enabled providers'))
                        ->extraAttributes(['data-testid' => 'admin-enabled-socialite-providers'])
                        ->options(fn (): array => collect(SocialiteService::providers())
                            ->mapWithKeys(fn (array $provider): array => [
                                $provider['name'] => $provider['label'],
                            ])
                            ->all())
                        ->nestedRecursiveRule(fn (CheckboxList $component) => Rule::in(
                            array_keys($component->getEnabledOptions()),
                        ))
                        ->helperText(__('Provider credentials must also be configured in the deployment environment.'))
                        ->columns(2)
                        ->columnSpanFull(),
                ]),
            Section::make(__('Magic Link Authentication'))
                ->description(__('Configure passwordless authentication by email.'))
                ->icon(Heroicon::OutlinedKey)
                ->schema([
                    Toggle::make('magic_link_enabled')
                        ->label(__('Enable magic link authentication')),
                    TextInput::make('magic_link_expiry')
                        ->label(__('Link expiry'))
                        ->integer()
                        ->required()
                        ->minValue(1)
                        ->suffix(__('minutes')),
                ])
                ->columns(1),
            Section::make(__('auth::auth.notifications.title'))
                ->description(__('auth::auth.notifications.description'))
                ->icon(Heroicon::OutlinedBellAlert)
                ->schema([
                    Toggle::make('login_notification_enabled')
                        ->label(__('auth::auth.notifications.login-enabled'))
                        ->helperText(__('auth::auth.notifications.login-help')),
                ])
                ->columns(1),
        ]);
    }
}
