<?php

namespace Amzad\LaravelPurchasable\Filament\Resources;

use Amzad\LaravelPurchasable\Filament\Concerns\HasCreatedAtRangeFilter;
use Amzad\LaravelPurchasable\Filament\Exports\OrderExporter;
use Amzad\LaravelPurchasable\Filament\PurchasablePlugin;
use Amzad\LaravelPurchasable\Filament\Resources\OrderResource\Pages\ListOrders;
use Amzad\LaravelPurchasable\Filament\Resources\OrderResource\Pages\ViewOrder;
use Amzad\LaravelPurchasable\Models\Order;
use Amzad\LaravelPurchasable\States\Order\OrderState;
use BackedEnum;
use Barryvdh\DomPDF\Facade\Pdf;
use Dompdf\Dompdf;
use Filament\Actions\Action;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

class OrderResource extends Resource
{
    use HasCreatedAtRangeFilter;

    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static ?string $recordTitleAttribute = 'order_number';

    public static function getNavigationGroup(): ?string
    {
        return PurchasablePlugin::get()->getNavigationGroup();
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Order')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('order_number')
                            ->label('Order #')
                            ->copyable(),
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn (OrderState $state): string => Str::headline($state->getValue()))
                            ->color(fn (OrderState $state): string => static::statusColor($state->getValue())),
                        TextEntry::make('quantity'),
                        TextEntry::make('amount')
                            ->money(fn (Order $record): string => $record->currency),
                        TextEntry::make('paid_at')
                            ->dateTime()
                            ->placeholder('—'),
                        TextEntry::make('created_at')
                            ->dateTime(),
                    ]),
                Section::make('Buyer & purchasable')
                    ->columns(2)
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('buyer_type')
                                    ->label('Buyer type')
                                    ->formatStateUsing(fn (string $state): string => class_basename($state)),
                                TextEntry::make('buyer_id')
                                    ->label('Buyer ID'),
                            ]),
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('purchasable_type')
                                    ->label('Purchasable type')
                                    ->formatStateUsing(fn (string $state): string => class_basename($state)),
                                TextEntry::make('purchasable_id')
                                    ->label('Purchasable ID'),
                            ]),
                    ]),
                Section::make('Meta')
                    ->collapsed(fn (Order $record): bool => blank($record->meta))
                    ->schema([
                        TextEntry::make('meta')
                            ->hiddenLabel()
                            ->getStateUsing(fn (Order $record): string => filled($record->meta)
                                ? json_encode($record->meta, JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE)
                                : 'No metadata')
                            ->extraAttributes(['style' => 'white-space: pre-wrap; font-family: monospace; font-size: 12px;']),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')
                    ->label('Order #')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (OrderState $state): string => Str::headline($state->getValue()))
                    ->color(fn (OrderState $state): string => static::statusColor($state->getValue()))
                    ->sortable(),
                TextColumn::make('buyer_type')
                    ->label('Buyer type')
                    ->formatStateUsing(fn (string $state): string => class_basename($state))
                    ->toggleable(),
                TextColumn::make('buyer_id')
                    ->label('Buyer ID')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('purchasable_type')
                    ->label('Purchasable type')
                    ->formatStateUsing(fn (string $state): string => class_basename($state))
                    ->toggleable(),
                TextColumn::make('purchasable_id')
                    ->label('Purchasable ID')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('amount')
                    ->money(fn (Order $record): string => $record->currency)
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
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                        'failed' => 'Failed',
                        'cancelled' => 'Cancelled',
                        'refunded' => 'Refunded',
                        'partially_refunded' => 'Partially refunded',
                    ]),
                static::morphFilter('buyer', 'buyer_type', 'buyer_id'),
                static::morphFilter('purchasable', 'purchasable_type', 'purchasable_id'),
                static::createdAtRangeFilter(),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                static::invoiceAction(),
            ])
            ->toolbarActions([
                ExportBulkAction::make()
                    ->exporter(OrderExporter::class),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function invoiceAction(): Action
    {
        return Action::make('invoice')
            ->label('Print invoice')
            ->icon(Heroicon::OutlinedDocumentArrowDown)
            ->color('gray')
            ->visible(fn (): bool => class_exists(Dompdf::class))
            ->action(function (Order $record) {
                $record->loadMissing('transactions');

                $html = view('purchasable::orders.invoice', ['order' => $record])->render();
                $pdf = Pdf::loadHTML($html)->output();

                return response()->streamDownload(
                    fn () => print $pdf,
                    "invoice-{$record->order_number}.pdf",
                );
            });
    }

    protected static function statusColor(string $status): string
    {
        return match ($status) {
            'paid' => 'success',
            'failed', 'cancelled' => 'danger',
            'refunded', 'partially_refunded' => 'warning',
            default => 'gray',
        };
    }

    protected static function morphFilter(string $name, string $typeColumn, string $idColumn): Filter
    {
        return Filter::make($name)
            ->label(Str::headline($name))
            ->schema([
                Select::make($typeColumn)
                    ->label(Str::headline($typeColumn))
                    ->options(fn (): array => static::morphTypeOptions($typeColumn))
                    ->native(false),
                TextInput::make($idColumn)
                    ->label(Str::headline($idColumn))
                    ->numeric(),
            ])
            ->query(fn (Builder $query, array $data): Builder => $query
                ->when($data[$typeColumn] ?? null, fn (Builder $query, string $type) => $query->where($typeColumn, $type))
                ->when($data[$idColumn] ?? null, fn (Builder $query, string $id) => $query->where($idColumn, $id)))
            ->indicateUsing(fn (array $data) => array_filter([
                $typeColumn => filled($data[$typeColumn] ?? null) ? Str::headline($typeColumn).': '.class_basename($data[$typeColumn]) : null,
                $idColumn => filled($data[$idColumn] ?? null) ? Str::headline($idColumn).': '.$data[$idColumn] : null,
            ]));
    }

    /**
     * @return array<string, string>
     */
    protected static function morphTypeOptions(string $column): array
    {
        return Order::query()
            ->whereNotNull($column)
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->mapWithKeys(fn (string $type): array => [$type => class_basename($type)])
            ->all();
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }
}
