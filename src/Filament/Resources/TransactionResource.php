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
use Illuminate\Support\Str;

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

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Transaction')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('transaction_number')
                            ->label('Transaction #')
                            ->copyable(),
                        TextEntry::make('order.order_number')
                            ->label('Order #')
                            ->url(fn (Transaction $record): string => OrderResource::getUrl('view', ['record' => $record->order])),
                        TextEntry::make('type')
                            ->badge()
                            ->formatStateUsing(fn (TransactionType $state): string => Str::headline($state->value))
                            ->color(fn (TransactionType $state): string => $state === TransactionType::Refund ? 'warning' : 'primary'),
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn (TransactionStatus $state): string => Str::headline($state->value))
                            ->color(fn (TransactionStatus $state): string => static::statusColor($state)),
                        TextEntry::make('gateway')
                            ->formatStateUsing(fn (?string $state): ?string => static::sanitizeUtf8($state)),
                        TextEntry::make('gateway_transaction_id')
                            ->label('Gateway transaction ID')
                            ->formatStateUsing(fn (?string $state): ?string => static::sanitizeUtf8($state))
                            ->copyable()
                            ->placeholder('—'),
                        TextEntry::make('amount')
                            ->money(fn (Transaction $record): string => $record->currency),
                        TextEntry::make('paid_at')
                            ->dateTime()
                            ->placeholder('—'),
                        TextEntry::make('created_at')
                            ->dateTime(),
                    ]),
                Section::make('Gateway response')
                    ->collapsed(fn (Transaction $record): bool => blank($record->response_payload))
                    ->schema([
                        TextEntry::make('response_payload')
                            ->hiddenLabel()
                            ->getStateUsing(fn (Transaction $record): string => filled($record->response_payload)
                                ? json_encode($record->response_payload, JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE)
                                : 'No response payload')
                            ->extraAttributes(['style' => 'white-space: pre-wrap; font-family: monospace; font-size: 12px;']),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('transaction_number')
                    ->label('Transaction #')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('order.order_number')
                    ->label('Order #')
                    ->searchable()
                    ->sortable()
                    ->url(fn (Transaction $record): string => OrderResource::getUrl('view', ['record' => $record->order])),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (TransactionType $state): string => Str::headline($state->value))
                    ->color(fn (TransactionType $state): string => $state === TransactionType::Refund ? 'warning' : 'primary')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (TransactionStatus $state): string => Str::headline($state->value))
                    ->color(fn (TransactionStatus $state): string => static::statusColor($state))
                    ->sortable(),
                TextColumn::make('gateway')
                    ->formatStateUsing(fn (?string $state): ?string => static::sanitizeUtf8($state))
                    ->toggleable(),
                TextColumn::make('gateway_transaction_id')
                    ->label('Gateway transaction ID')
                    ->formatStateUsing(fn (?string $state): ?string => static::sanitizeUtf8($state))
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('amount')
                    ->money(fn (Transaction $record): string => $record->currency)
                    ->sortable(),
                TextColumn::make('paid_at')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        TransactionType::Charge->value => 'Charge',
                        TransactionType::Refund->value => 'Refund',
                    ]),
                SelectFilter::make('status')
                    ->options([
                        TransactionStatus::Pending->value => 'Pending',
                        TransactionStatus::Success->value => 'Success',
                        TransactionStatus::Failed->value => 'Failed',
                    ]),
                SelectFilter::make('order')
                    ->relationship('order', 'order_number')
                    ->searchable()
                    ->preload()
                    ->label('Order'),
                SelectFilter::make('gateway')
                    ->options(fn (): array => static::distinctColumnOptions('gateway')),
                SelectFilter::make('currency')
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
