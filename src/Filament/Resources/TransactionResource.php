<?php

namespace Amzad\LaravelPurchasable\Filament\Resources;

use Amzad\LaravelPurchasable\Enums\TransactionStatus;
use Amzad\LaravelPurchasable\Enums\TransactionType;
use Amzad\LaravelPurchasable\Filament\Concerns\HasCreatedAtRangeFilter;
use Amzad\LaravelPurchasable\Filament\Concerns\SanitizesUtf8;
use Amzad\LaravelPurchasable\Filament\Exports\TransactionExporter;
use Amzad\LaravelPurchasable\Filament\PurchasablePlugin;
use Amzad\LaravelPurchasable\Filament\Resources\TransactionResource\Pages\ListTransactions;
use Amzad\LaravelPurchasable\Filament\Resources\TransactionResource\Pages\ViewTransaction;
use Amzad\LaravelPurchasable\Models\Transaction;
use BackedEnum;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TransactionResource extends Resource
{
    use HasCreatedAtRangeFilter;
    use SanitizesUtf8;

    protected static ?string $model = Transaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?string $recordTitleAttribute = 'transaction_number';

    public static function getNavigationGroup(): ?string
    {
        return PurchasablePlugin::get()->getNavigationGroup();
    }

    public static function getModelLabel(): string
    {
        return __('Transaction');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Transactions');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Transaction'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('transaction_number')
                            ->label(__('Transaction #'))
                            ->copyable(),
                        TextEntry::make('order.order_number')
                            ->label(__('Order #'))
                            ->url(fn (Transaction $record): string => OrderResource::getUrl('view', ['record' => $record->order])),
                        TextEntry::make('type')
                            ->label(__('Type'))
                            ->badge()
                            ->formatStateUsing(fn (TransactionType $state): string => $state->label())
                            ->color(fn (TransactionType $state): string => $state === TransactionType::Refund ? 'warning' : 'primary'),
                        TextEntry::make('status')
                            ->label(__('Status'))
                            ->badge()
                            ->formatStateUsing(fn (TransactionStatus $state): string => $state->label())
                            ->color(fn (TransactionStatus $state): string => static::statusColor($state)),
                        TextEntry::make('gateway')
                            ->label(__('Gateway'))
                            ->formatStateUsing(fn (?string $state): ?string => static::sanitizeUtf8($state)),
                        TextEntry::make('gateway_transaction_id')
                            ->label(__('Gateway transaction ID'))
                            ->formatStateUsing(fn (?string $state): ?string => static::sanitizeUtf8($state))
                            ->copyable()
                            ->placeholder('—'),
                        TextEntry::make('amount')
                            ->label(__('Amount'))
                            ->money(fn (Transaction $record): string => $record->currency),
                        TextEntry::make('paid_at')
                            ->label(__('Paid at'))
                            ->dateTime()
                            ->placeholder('—'),
                        TextEntry::make('created_at')
                            ->label(__('Created at'))
                            ->dateTime(),
                    ]),
                Section::make(__('Gateway response'))
                    ->collapsed(fn (Transaction $record): bool => blank($record->response_payload))
                    ->schema([
                        TextEntry::make('response_payload')
                            ->hiddenLabel()
                            ->getStateUsing(fn (Transaction $record): string => filled($record->response_payload)
                                ? json_encode($record->response_payload, JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE)
                                : __('No response payload'))
                            ->extraAttributes(['style' => 'white-space: pre-wrap; font-family: monospace; font-size: 12px;']),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('transaction_number')
                    ->label(__('Transaction #'))
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('order.order_number')
                    ->label(__('Order #'))
                    ->searchable()
                    ->sortable()
                    ->url(fn (Transaction $record): string => OrderResource::getUrl('view', ['record' => $record->order])),
                TextColumn::make('type')
                    ->label(__('Type'))
                    ->badge()
                    ->formatStateUsing(fn (TransactionType $state): string => $state->label())
                    ->color(fn (TransactionType $state): string => $state === TransactionType::Refund ? 'warning' : 'primary')
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->formatStateUsing(fn (TransactionStatus $state): string => $state->label())
                    ->color(fn (TransactionStatus $state): string => static::statusColor($state))
                    ->sortable(),
                TextColumn::make('gateway')
                    ->label(__('Gateway'))
                    ->formatStateUsing(fn (?string $state): ?string => static::sanitizeUtf8($state))
                    ->toggleable(),
                TextColumn::make('gateway_transaction_id')
                    ->label(__('Gateway transaction ID'))
                    ->formatStateUsing(fn (?string $state): ?string => static::sanitizeUtf8($state))
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('amount')
                    ->label(__('Amount'))
                    ->money(fn (Transaction $record): string => $record->currency)
                    ->sortable(),
                TextColumn::make('paid_at')
                    ->label(__('Paid at'))
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label(__('Created at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('Type'))
                    ->options([
                        TransactionType::Charge->value => TransactionType::Charge->label(),
                        TransactionType::Refund->value => TransactionType::Refund->label(),
                    ]),
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options([
                        TransactionStatus::Pending->value => TransactionStatus::Pending->label(),
                        TransactionStatus::Success->value => TransactionStatus::Success->label(),
                        TransactionStatus::Failed->value => TransactionStatus::Failed->label(),
                    ]),
                SelectFilter::make('order')
                    ->relationship('order', 'order_number')
                    ->searchable()
                    ->preload()
                    ->label(__('Order')),
                SelectFilter::make('gateway')
                    ->label(__('Gateway'))
                    ->options(fn (): array => static::distinctColumnOptions('gateway')),
                SelectFilter::make('currency')
                    ->label(__('Currency'))
                    ->options(fn (): array => static::distinctColumnOptions('currency')),
                static::createdAtRangeFilter(),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                ExportBulkAction::make()
                    ->exporter(TransactionExporter::class),
            ])
            ->defaultSort('created_at', 'desc');
    }

    protected static function statusColor(TransactionStatus $status): string
    {
        return match ($status) {
            TransactionStatus::Success => 'success',
            TransactionStatus::Failed => 'danger',
            TransactionStatus::Pending => 'gray',
        };
    }

    /**
     * @return array<string, string>
     */
    protected static function distinctColumnOptions(string $column): array
    {
        return Transaction::query()
            ->whereNotNull($column)
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->mapWithKeys(function (string $value): array {
                $clean = static::sanitizeUtf8($value);

                return [$clean => $clean];
            })
            ->all();
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListTransactions::route('/'),
            'view' => ViewTransaction::route('/{record}'),
        ];
    }
}
