<?php

namespace App\Filament\Resources\AppCategoryResource\Pages;

use App\Filament\Resources\AppCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAppCategory extends EditRecord
{
    protected static string $resource = AppCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
