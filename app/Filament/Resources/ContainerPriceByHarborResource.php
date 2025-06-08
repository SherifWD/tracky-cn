<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContainerPriceByHarborResource\Pages;
use App\Filament\Resources\ContainerPriceByHarborResource\RelationManagers;
use App\Models\ContainerPriceByHarbor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ContainerPriceByHarborResource extends Resource
{
    protected static ?string $model = ContainerPriceByHarbor::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('container_id')
                ->relationship(name: 'container', titleAttribute: 'size')
                    ->default(null),
                Forms\Components\Select::make('harbor_id')
                ->relationship(name: 'harbor', titleAttribute: 'name')
                    ->default(null),
                Forms\Components\TextInput::make('base_price')
                    ->numeric()
                    ->default(null),
                Forms\Components\DatePicker::make('date'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('container.size')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('harbor.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('base_price')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),
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
            'index' => Pages\ListContainerPriceByHarbors::route('/'),
            'create' => Pages\CreateContainerPriceByHarbor::route('/create'),
            'edit' => Pages\EditContainerPriceByHarbor::route('/{record}/edit'),
        ];
    }
}
