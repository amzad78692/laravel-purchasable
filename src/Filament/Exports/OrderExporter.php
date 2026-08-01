<?php

namespace Amzad\LaravelPurchasable\Filament\Exports;

use Amzad\LaravelPurchasable\Models\Order;
use Amzad\LaravelPurchasable\States\Order\OrderState;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

class OrderExporter extends Exporter
{
    protected static ?string $model = Order::class;

    /**
     * @return array<ExportColumn>
     */
    public static function getColumns(): array
    {
        return [
            ExportColumn::make('order_number')
                ->label('Order #'),
            ExportColumn::make('status')
                ->formatStateUsing(fn (OrderState $state): string => Str::headline($state->getValue())),
            ExportColumn::make('buyer_type')
                ->label('Buyer type')
                ->formatStateUsing(fn (string $state): string => class_basename($state)),
            ExportColumn::make('buyer_id')
                ->label('Buyer ID'),
            ExportColumn::make('purchasable_type')
                ->label('Purchasable type')
                ->formatStateUsing(fn (string $state): string => class_basename($state)),
            ExportColumn::make('purchasable_id')
                ->label('Purchasable ID'),
            ExportColumn::make('quantity'),
            ExportColumn::make('amount'),
            ExportColumn::make('currency'),
            ExportColumn::make('paid_at'),
            ExportColumn::make('created_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your order export has completed and '.Number::format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
