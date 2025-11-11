<?php

namespace App\Filament\Resources\ShippingLineIconResource\Pages;

use App\Filament\Resources\ShippingLineIconResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListShippingLineIcons extends ListRecords
{
    protected static string $resource = ShippingLineIconResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
