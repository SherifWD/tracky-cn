<?php

namespace App\Filament\Resources\ProjectSettingResource\Pages;

use App\Filament\Resources\ProjectSettingResource;
use App\Models\ProjectSetting;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProjectSettings extends ListRecords
{
    protected static string $resource = ProjectSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->visible(fn (): bool => ! ProjectSetting::query()->exists()),
        ];
    }
}
