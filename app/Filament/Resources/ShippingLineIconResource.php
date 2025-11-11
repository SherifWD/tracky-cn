<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShippingLineIconResource\Pages;
use App\Models\ShippingLineIcon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ShippingLineIconResource extends Resource
{
    protected static ?string $model = ShippingLineIcon::class;

    protected static ?string $navigationIcon = 'heroicon-o-flag';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Shipping Line Icon')
                    ->schema([
                        Forms\Components\FileUpload::make('icon')
                            ->label('Icon')
                            ->required()
                            ->image()
                            ->disk('local')
                            ->directory('images')
                            ->maxSize(2048)
                            ->helperText('PNG/SVG under 2 MB stored under /public/images/shipping-line-icons'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('icon')
                    ->label('Icon')
                    ->square()
                    ->height(48),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListShippingLineIcons::route('/'),
            'create' => Pages\CreateShippingLineIcon::route('/create'),
            'edit' => Pages\EditShippingLineIcon::route('/{record}/edit'),
        ];
    }
}
