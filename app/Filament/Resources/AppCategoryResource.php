<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AppCategoryResource\Pages;
use App\Filament\Resources\AppCategoryResource\RelationManagers\AppsRelationManager;
use App\Models\AppCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AppCategoryResource extends Resource
{
    protected static ?string $model = AppCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Category Details')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\FileUpload::make('icon')
                            ->label('Icon')
                            ->image()
                            ->disk('local')
                            ->directory('images/app-categories')
                            ->maxSize(2048)
                            ->helperText('Upload a square image (max 2 MB).'),
                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(4)
                            ->columnSpanFull()
                            ->maxLength(255)
                            ->placeholder('Short description shown in the mobile app.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->sortable()
                    ->searchable()
                    ->limit(40)
                    ->wrap(),
                Tables\Columns\ImageColumn::make('icon')
                    ->label('Icon')
                    ->square()
                    ->height(40)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('apps_count')
                    ->counts('apps')
                    ->label('Apps')
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->toggleable()
                    ->limit(60)
                    ->wrap(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            AppsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAppCategories::route('/'),
            'create' => Pages\CreateAppCategory::route('/create'),
            'edit' => Pages\EditAppCategory::route('/{record}/edit'),
        ];
    }
}
