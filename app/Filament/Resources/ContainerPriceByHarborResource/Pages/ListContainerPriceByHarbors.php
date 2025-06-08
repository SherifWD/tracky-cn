<?php

namespace App\Filament\Resources\ContainerPriceByHarborResource\Pages;

use App\Filament\Resources\ContainerPriceByHarborResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListContainerPriceByHarbors extends ListRecords
{
    protected static string $resource = ContainerPriceByHarborResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
