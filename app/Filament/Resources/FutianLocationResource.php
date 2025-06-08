<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FutianLocationResource\Pages;
use App\Filament\Resources\FutianLocationResource\RelationManagers;
use App\Models\FutianLocation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class FutianLocationResource extends Resource
{
    protected static ?string $model = FutianLocation::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('lat')
                    ->maxLength(255)
                    ->default(null),
                Forms\Components\TextInput::make('lng')
                    ->maxLength(255)
                    ->default(null),
                Forms\Components\FileUpload::make('image')
                    ->image(),
                Forms\Components\TextInput::make('radius')
                    ->maxLength(255)
                    ->default(null),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('lat')
                    ->searchable(),
                Tables\Columns\TextColumn::make('lng')
                    ->searchable(),
                Tables\Columns\ImageColumn::make('image'),
                Tables\Columns\TextColumn::make('radius')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFutianLocations::route('/'),
            'create' => Pages\CreateFutianLocation::route('/create'),
            'edit' => Pages\EditFutianLocation::route('/{record}/edit'),
        ];
    }
}
