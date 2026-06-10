<?php

namespace App\Filament\Resources\ProjectSettingResource\Pages;

use App\Filament\Resources\ProjectSettingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProjectSetting extends CreateRecord
{
    protected static string $resource = ProjectSettingResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['singleton'] = true;

        return $data;
    }
}
