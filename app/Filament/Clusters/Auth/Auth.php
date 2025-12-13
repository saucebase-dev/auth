<?php

namespace Modules\Auth\Filament\Clusters\Auth;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;

class Auth extends Cluster
{
    protected static ?string $navigationLabel = 'Authentication';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPower;

    protected static ?int $navigationSort = 10;
}
