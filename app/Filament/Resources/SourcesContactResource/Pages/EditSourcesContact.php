<?php

namespace App\Filament\Resources\SourcesContactResource\Pages;

use App\Filament\Resources\SourcesContactResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSourcesContact extends EditRecord
{
    protected static string $resource = SourcesContactResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
