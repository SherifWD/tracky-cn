<?php

namespace App\Filament\Resources\ReceiptPaymentResource\Pages;

use App\Filament\Resources\ReceiptPaymentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListReceiptPayments extends ListRecords
{
    protected static string $resource = ReceiptPaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
