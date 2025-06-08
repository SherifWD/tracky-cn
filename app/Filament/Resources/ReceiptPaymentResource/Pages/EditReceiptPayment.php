<?php

namespace App\Filament\Resources\ReceiptPaymentResource\Pages;

use App\Filament\Resources\ReceiptPaymentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditReceiptPayment extends EditRecord
{
    protected static string $resource = ReceiptPaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
