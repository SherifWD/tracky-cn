<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReceiptPaymentResource\Pages;
use App\Filament\Resources\ReceiptPaymentResource\RelationManagers;
use App\Models\ReceiptPayment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ReceiptPaymentResource extends Resource
{
    protected static ?string $model = ReceiptPayment::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('from_country_id')
                    ->numeric()
                    ->default(null),
                Forms\Components\TextInput::make('to_country_id')
                    ->numeric()
                    ->default(null),
                Forms\Components\TextInput::make('original_price')
                    ->numeric()
                    ->default(null),
                Forms\Components\TextInput::make('after_commission_price')
                    ->numeric()
                    ->default(null),
                Forms\Components\TextInput::make('usd_conversion')
                    ->numeric()
                    ->default(null),
                Forms\Components\TextInput::make('user_id')
                    ->numeric()
                    ->default(null),
                Forms\Components\TextInput::make('to_country')
                    ->maxLength(255)
                    ->default(null),
                Forms\Components\TextInput::make('from_country')
                    ->maxLength(255)
                    ->default(null),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('from_country_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('to_country_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('original_price')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('after_commission_price')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('usd_conversion')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('user_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('to_country')
                    ->searchable(),
                Tables\Columns\TextColumn::make('from_country')
                    ->searchable(),
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
            'index' => Pages\ListReceiptPayments::route('/'),
            'create' => Pages\CreateReceiptPayment::route('/create'),
            'edit' => Pages\EditReceiptPayment::route('/{record}/edit'),
        ];
    }
}
