<?php

namespace App\Filament\Resources\ShippingContainerResource\Pages;

use App\Filament\Resources\ShippingContainerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditShippingContainer extends EditRecord
{
    protected static string $resource = ShippingContainerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
