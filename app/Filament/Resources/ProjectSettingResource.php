<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProjectSettingResource\Pages;
use App\Models\ProjectSetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProjectSettingResource extends Resource
{
    protected static ?string $model = ProjectSetting::class;

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Project Settings';

    protected static ?string $modelLabel = 'Project Setting';

    protected static ?string $pluralModelLabel = 'Project Settings';

    public static function canCreate(): bool
    {
        return ! ProjectSetting::query()->exists();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Contact')
                    ->schema([
                        Forms\Components\TextInput::make('whatsapp_number')
                            ->label('WhatsApp number')
                            ->tel()
                            ->required()
                            ->maxLength(255),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('whatsapp_number')
                    ->label('WhatsApp number')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjectSettings::route('/'),
            'create' => Pages\CreateProjectSetting::route('/create'),
            'edit' => Pages\EditProjectSetting::route('/{record}/edit'),
        ];
    }
}
