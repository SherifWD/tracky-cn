<?php

namespace App\Filament\Resources\AppCategoryResource\Pages;

use App\Filament\Resources\AppCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAppCategories extends ListRecords
{
    protected static string $resource = AppCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
