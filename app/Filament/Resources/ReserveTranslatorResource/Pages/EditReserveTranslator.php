<?php

namespace App\Filament\Resources\ReserveTranslatorResource\Pages;

use App\Filament\Resources\ReserveTranslatorResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditReserveTranslator extends EditRecord
{
    protected static string $resource = ReserveTranslatorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
