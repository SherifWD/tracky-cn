<?php

namespace App\Filament\Resources\HarborLocationResource\Pages;

use App\Filament\Resources\HarborLocationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListHarborLocations extends ListRecords
{
    protected static string $resource = HarborLocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
