<?php

namespace App\Filament\Resources\ReservedShippingResource\Pages;

use App\Filament\Resources\ReservedShippingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListReservedShippings extends ListRecords
{
    protected static string $resource = ReservedShippingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
