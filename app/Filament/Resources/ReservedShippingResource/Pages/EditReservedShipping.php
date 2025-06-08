<?php

namespace App\Filament\Resources\ReservedShippingResource\Pages;

use App\Filament\Resources\ReservedShippingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditReservedShipping extends EditRecord
{
    protected static string $resource = ReservedShippingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
