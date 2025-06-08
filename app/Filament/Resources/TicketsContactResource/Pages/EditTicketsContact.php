<?php

namespace App\Filament\Resources\TicketsContactResource\Pages;

use App\Filament\Resources\TicketsContactResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTicketsContact extends EditRecord
{
    protected static string $resource = TicketsContactResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
