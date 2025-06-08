<?php

namespace App\Filament\Resources\HarborLocationResource\Pages;

use App\Filament\Resources\HarborLocationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditHarborLocation extends EditRecord
{
    protected static string $resource = HarborLocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
