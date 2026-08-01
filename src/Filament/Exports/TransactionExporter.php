<?php

namespace Amzad\LaravelPurchasable\Filament\Exports;

use Amzad\LaravelPurchasable\Enums\TransactionStatus;
use Amzad\LaravelPurchasable\Enums\TransactionType;
use Amzad\LaravelPurchasable\Models\Transaction;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

class TransactionExporter extends Exporter
{
    protected static ?string $model = Transaction::class;

    /**
     * @return array<ExportColumn>
     */
    public static function getColumns(): array
    {
        return [
            ExportColumn::make('transaction_number')
                ->label('Transaction #'),
            ExportColumn::make('order.order_number')
                ->label('Order #'),
            ExportColumn::make('type')
                ->formatStateUsing(fn (TransactionType $state): string => Str::headline($state->value)),
            ExportColumn::make('gateway'),
            ExportColumn::make('gateway_transaction_id')
                ->label('Gateway transaction ID'),
            ExportColumn::make('status')
                ->formatStateUsing(fn (TransactionStatus $state): string => Str::headline($state->value)),
            ExportColumn::make('amount'),
            ExportColumn::make('currency'),
            ExportColumn::make('paid_at'),
            ExportColumn::make('created_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your transaction export has completed and '.Number::format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
