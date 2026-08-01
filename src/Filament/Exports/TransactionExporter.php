<?php

namespace Amzad\LaravelPurchasable\Filament\Exports;

use Amzad\LaravelPurchasable\Enums\TransactionStatus;
use Amzad\LaravelPurchasable\Enums\TransactionType;
use Amzad\LaravelPurchasable\Models\Transaction;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

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
                ->label(__('Transaction #')),
            ExportColumn::make('order.order_number')
                ->label(__('Order #')),
            ExportColumn::make('type')
                ->label(__('Type'))
                ->formatStateUsing(fn (TransactionType $state): string => $state->label()),
            ExportColumn::make('gateway')
                ->label(__('Gateway'))
                ->formatStateUsing(fn (?string $state): ?string => static::preventFormulaInjection($state)),
            ExportColumn::make('gateway_transaction_id')
                ->label(__('Gateway transaction ID'))
                ->formatStateUsing(fn (?string $state): ?string => static::preventFormulaInjection($state)),
            ExportColumn::make('status')
                ->label(__('Status'))
                ->formatStateUsing(fn (TransactionStatus $state): string => $state->label()),
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
            ? __('Your transaction export has completed and :count row exported.', ['count' => Number::format($export->successful_rows)])
            : __('Your transaction export has completed and :count rows exported.', ['count' => Number::format($export->successful_rows)]);

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.($failedRowsCount === 1
                ? __(':count row failed to export.', ['count' => Number::format($failedRowsCount)])
                : __(':count rows failed to export.', ['count' => Number::format($failedRowsCount)]));
        }

        return $body;
    }

    /**
     * Neutralizes CSV/XLSX formula injection in gateway-supplied values: a
     * leading =, +, -, or @ is interpreted as a formula by Excel/Sheets/
     * LibreOffice when the file is opened, so it's escaped with a leading
     * apostrophe to force literal-text interpretation.
     */
    protected static function preventFormulaInjection(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return preg_match('/^[=+\-@]/', $value) ? "'".$value : $value;
    }
}
