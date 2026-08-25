<?php

namespace App\Filament\Admin\Resources\DunningPolicies\Pages;

use App\Filament\Admin\Resources\DunningPolicies\DunningPolicyResource;
use Filament\Resources\Pages\ListRecords;

class ListDunningPolicies extends ListRecords
{
    protected static string $resource = DunningPolicyResource::class;
}