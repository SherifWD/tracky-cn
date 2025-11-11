<?php

namespace App\Filament\Resources\ShippingLineIconResource\Pages;

use App\Filament\Resources\ShippingLineIconResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditShippingLineIcon extends EditRecord
{
    protected static string $resource = ShippingLineIconResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
