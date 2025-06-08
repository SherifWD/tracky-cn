<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReservedShippingResource\Pages;
use App\Filament\Resources\ReservedShippingResource\RelationManagers;
use App\Models\ReservedShipping;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ReservedShippingResource extends Resource
{
    protected static ?string $model = ReservedShipping::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('container_id')
                    ->numeric()
                    ->default(null),
                Forms\Components\TextInput::make('harbor_id_from')
                    ->numeric()
                    ->default(null),
                Forms\Components\TextInput::make('harbor_id_to')
                    ->numeric()
                    ->default(null),
                Forms\Components\TextInput::make('container_price_id')
                    ->numeric()
                    ->default(null),
                Forms\Components\DatePicker::make('date'),
                Forms\Components\TextInput::make('base_price')
                    ->numeric()
                    ->default(null),
                Forms\Components\TextInput::make('user_id')
                    ->numeric()
                    ->default(null),
                Forms\Components\Toggle::make('status')
                    ->required(),
                Forms\Components\TextInput::make('reservation_string')
                    ->maxLength(255)
                    ->default(null),
                Forms\Components\TextInput::make('subscription_id')
                    ->maxLength(255)
                    ->default(null),
                Forms\Components\TextInput::make('track_number')
                    ->maxLength(255)
                    ->default(null),
                Forms\Components\TextInput::make('carrier_code')
                    ->maxLength(255)
                    ->default(null),
                Forms\Components\TextInput::make('port_code')
                    ->maxLength(255)
                    ->default(null),
                Forms\Components\TextInput::make('is_export')
                    ->required(),
                Forms\Components\TextInput::make('count')
                    ->required()
                    ->numeric()
                    ->default(1),
                Forms\Components\TextInput::make('container_no')
                    ->maxLength(255)
                    ->default(null),
                Forms\Components\TextInput::make('vessel_name')
                    ->maxLength(255)
                    ->default(null),
                Forms\Components\TextInput::make('voyage')
                    ->maxLength(255)
                    ->default(null),
                Forms\Components\TextInput::make('imo_number')
                    ->maxLength(255)
                    ->default(null),
                Forms\Components\TextInput::make('call_sign')
                    ->maxLength(255)
                    ->default(null),
                Forms\Components\TextInput::make('terminal_code')
                    ->maxLength(255)
                    ->default(null),
                Forms\Components\TextInput::make('terminal_name')
                    ->maxLength(255)
                    ->default(null),
                Forms\Components\DateTimePicker::make('eta'),
                Forms\Components\DateTimePicker::make('etd'),
                Forms\Components\DateTimePicker::make('ata'),
                Forms\Components\DateTimePicker::make('atd'),
                Forms\Components\TextInput::make('ship_name')
                    ->maxLength(255)
                    ->default(null),
                Forms\Components\TextInput::make('ship_lat')
                    ->numeric()
                    ->default(null),
                Forms\Components\TextInput::make('ship_lon')
                    ->numeric()
                    ->default(null),
                Forms\Components\TextInput::make('ship_status')
                    ->maxLength(255)
                    ->default(null),
                Forms\Components\TextInput::make('ship_speed')
                    ->numeric()
                    ->default(null),
                Forms\Components\TextInput::make('ship_eta')
                    ->maxLength(255)
                    ->default(null),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('container_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('harbor_id_from')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('harbor_id_to')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('container_price_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('base_price')
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
                Tables\Columns\IconColumn::make('status')
                    ->boolean(),
                Tables\Columns\TextColumn::make('reservation_string')
                    ->searchable(),
                Tables\Columns\TextColumn::make('subscription_id')
                    ->searchable(),
                Tables\Columns\TextColumn::make('track_number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('carrier_code')
                    ->searchable(),
                Tables\Columns\TextColumn::make('port_code')
                    ->searchable(),
                Tables\Columns\TextColumn::make('is_export'),
                Tables\Columns\TextColumn::make('count')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('container_no')
                    ->searchable(),
                Tables\Columns\TextColumn::make('vessel_name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('voyage')
                    ->searchable(),
                Tables\Columns\TextColumn::make('imo_number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('call_sign')
                    ->searchable(),
                Tables\Columns\TextColumn::make('terminal_code')
                    ->searchable(),
                Tables\Columns\TextColumn::make('terminal_name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('eta')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('etd')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('ata')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('atd')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('ship_name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('ship_lat')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('ship_lon')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('ship_status')
                    ->searchable(),
                Tables\Columns\TextColumn::make('ship_speed')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('ship_eta')
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
            'index' => Pages\ListReservedShippings::route('/'),
            'create' => Pages\CreateReservedShipping::route('/create'),
            'edit' => Pages\EditReservedShipping::route('/{record}/edit'),
        ];
    }
}
