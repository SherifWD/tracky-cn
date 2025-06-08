<?php

namespace App\Filament\Resources\FutianLocationResource\Pages;

use App\Filament\Resources\FutianLocationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFutianLocation extends EditRecord
{
    protected static string $resource = FutianLocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
