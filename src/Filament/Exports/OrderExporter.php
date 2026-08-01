<?php

namespace Amzad\LaravelPurchasable\Filament\Exports;

use Amzad\LaravelPurchasable\Models\Order;
use Amzad\LaravelPurchasable\States\Order\OrderState;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

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
                ->label(__('Order #')),
            ExportColumn::make('status')
                ->label(__('Status'))
                ->formatStateUsing(fn (OrderState $state): string => $state->label()),
            ExportColumn::make('buyer_type')
                ->label(__('Buyer type'))
                ->formatStateUsing(fn (string $state): string => class_basename($state)),
            ExportColumn::make('buyer_id')
                ->label(__('Buyer ID')),
            ExportColumn::make('purchasable_type')
                ->label(__('Purchasable type'))
                ->formatStateUsing(fn (string $state): string => class_basename($state)),
            ExportColumn::make('purchasable_id')
                ->label(__('Purchasable ID')),
            ExportColumn::make('quantity')
                ->label(__('Quantity')),
            ExportColumn::make('amount')
                ->label(__('Amount')),
            ExportColumn::make('currency')
                ->label(__('Currency')),
            ExportColumn::make('paid_at')
                ->label(__('Paid at')),
            ExportColumn::make('created_at')
                ->label(__('Created at')),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = $export->successful_rows === 1
            ? __('Your order export has completed and :count row exported.', ['count' => Number::format($export->successful_rows)])
            : __('Your order export has completed and :count rows exported.', ['count' => Number::format($export->successful_rows)]);

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.($failedRowsCount === 1
                ? __(':count row failed to export.', ['count' => Number::format($failedRowsCount)])
                : __(':count rows failed to export.', ['count' => Number::format($failedRowsCount)]));
        }

        return $body;
    }
}
