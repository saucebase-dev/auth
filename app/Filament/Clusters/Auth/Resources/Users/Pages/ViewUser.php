<?php

namespace Modules\Auth\Filament\Clusters\Auth\Resources\Users\Pages;

use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Modules\Auth\Filament\Clusters\Auth\Resources\Users\UserResource;
use STS\FilamentImpersonate\Actions\Impersonate;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Impersonate::make()->record($this->getRecord()),
        ];
    }
}
