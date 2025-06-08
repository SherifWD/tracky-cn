<?php

namespace App\Filament\Resources\TicketsContactResource\Pages;

use App\Filament\Resources\TicketsContactResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTicketsContacts extends ListRecords
{
    protected static string $resource = TicketsContactResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
