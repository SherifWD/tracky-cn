<?php

namespace App\Filament\Resources\ReserveTranslatorResource\Pages;

use App\Filament\Resources\ReserveTranslatorResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListReserveTranslators extends ListRecords
{
    protected static string $resource = ReserveTranslatorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
