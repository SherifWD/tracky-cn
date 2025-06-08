<?php

namespace App\Filament\Resources\ShippingContainerResource\Pages;

use App\Filament\Resources\ShippingContainerResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListShippingContainers extends ListRecords
{
    protected static string $resource = ShippingContainerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
