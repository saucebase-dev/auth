<?php

namespace Modules\Auth\Filament\Resources\Users\Pages;

use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Auth\Filament\Resources\Users\UserResource;
use STS\FilamentImpersonate\Actions\Impersonate;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
                ->authorize(fn (User $record): bool => UserResource::canDelete($record)),
            Impersonate::make()->record($this->getRecord()),
        ];
    }
}
