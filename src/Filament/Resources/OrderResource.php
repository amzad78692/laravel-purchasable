<?php

namespace Amzad\LaravelPurchasable\Filament\Resources;

use Amzad\LaravelPurchasable\Filament\Concerns\HasCreatedAtRangeFilter;
use Amzad\LaravelPurchasable\Filament\Exports\OrderExporter;
use Amzad\LaravelPurchasable\Filament\PurchasablePlugin;
use Amzad\LaravelPurchasable\Filament\Resources\OrderResource\Pages\ListOrders;
use Amzad\LaravelPurchasable\Filament\Resources\OrderResource\Pages\ViewOrder;
use Amzad\LaravelPurchasable\Filament\Resources\OrderResource\RelationManagers\ItemsRelationManager;
use Amzad\LaravelPurchasable\Models\Order;
use Amzad\LaravelPurchasable\States\Order\OrderState;
use BackedEnum;
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
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf;

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

    public static function getModelLabel(): string
    {
        return __('Order');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Orders');
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
                Section::make(__('Order'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('order_number')
                            ->label(__('Order #'))
                            ->copyable(),
                        TextEntry::make('status')
                            ->label(__('Status'))
                            ->badge()
                            ->formatStateUsing(fn (OrderState $state): string => $state->label())
                            ->color(fn (OrderState $state): string => static::statusColor($state->getValue())),
                        TextEntry::make('quantity')
                            ->label(__('Quantity')),
                        TextEntry::make('amount')
                            ->label(__('Amount'))
                            ->money(fn (Order $record): string => $record->currency),
                        TextEntry::make('paid_at')
                            ->label(__('Paid at'))
                            ->dateTime()
                            ->placeholder('—'),
                        TextEntry::make('created_at')
                            ->label(__('Created at'))
                            ->dateTime(),
                    ]),
                Section::make(__('Buyer & purchasable'))
                    ->columns(2)
                    ->schema([
                        Grid::make(1)
                            ->schema([
                                TextEntry::make('buyer_display')
                                    ->label(__('Buyer'))
                                    ->getStateUsing(fn (Order $record): string => $record->buyerDisplayName()),
                                TextEntry::make('buyer_type')
                                    ->label(__('Type & ID'))
                                    ->hiddenLabel()
                                    ->size(TextSize::Small)
                                    ->color('gray')
                                    ->formatStateUsing(fn (string $state, Order $record): string => class_basename($state).' #'.$record->buyer_id),
                            ]),
                        Grid::make(1)
                            ->visible(fn (Order $record): bool => ! $record->isMultiItem())
                            ->schema([
                                TextEntry::make('purchasable_display')
                                    ->label(__('Purchasable'))
                                    ->getStateUsing(fn (Order $record): string => $record->purchasableDisplayName()),
                                TextEntry::make('purchasable_type')
                                    ->label(__('Type & ID'))
                                    ->hiddenLabel()
                                    ->size(TextSize::Small)
                                    ->color('gray')
                                    ->formatStateUsing(fn (?string $state, Order $record): string => $state !== null ? class_basename($state).' #'.$record->purchasable_id : ''),
                            ]),
                    ]),
                Section::make(__('Meta'))
                    ->collapsed(fn (Order $record): bool => blank($record->meta))
                    ->schema([
                        TextEntry::make('meta')
                            ->hiddenLabel()
                            ->getStateUsing(fn (Order $record): string => filled($record->meta)
                                ? json_encode($record->meta, JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE)
                                : __('No metadata'))
                            ->extraAttributes(['style' => 'white-space: pre-wrap; font-family: monospace; font-size: 12px;']),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')
                    ->label(__('Order #'))
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->formatStateUsing(fn (OrderState $state): string => $state->label())
                    ->color(fn (OrderState $state): string => static::statusColor($state->getValue()))
                    ->sortable(),
                TextColumn::make('buyer_display')
                    ->label(__('Buyer'))
                    ->getStateUsing(fn (Order $record): string => $record->buyerDisplayName()),
                TextColumn::make('buyer_type')
                    ->label(__('Buyer type'))
                    ->formatStateUsing(fn (string $state): string => class_basename($state))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('buyer_id')
                    ->label(__('Buyer ID'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('purchasable_display')
                    ->label(__('Purchasable'))
                    ->getStateUsing(fn (Order $record): string => $record->isMultiItem() ? __('Multiple items') : $record->purchasableDisplayName()),
                TextColumn::make('purchasable_type')
                    ->label(__('Purchasable type'))
                    ->formatStateUsing(fn (?string $state): string => $state !== null ? class_basename($state) : '')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('purchasable_id')
                    ->label(__('Purchasable ID'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('amount')
                    ->label(__('Amount'))
                    ->money(fn (Order $record): string => $record->currency)
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
                SelectFilter::make('status')
                    ->options([
                        'pending' => __('Pending'),
                        'paid' => __('Paid'),
                        'failed' => __('Failed'),
                        'cancelled' => __('Cancelled'),
                        'refunded' => __('Refunded'),
                        'partially_refunded' => __('Partially refunded'),
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
            ->label(__('Print invoice'))
            ->icon(Heroicon::OutlinedDocumentArrowDown)
            ->color('gray')
            ->visible(fn (): bool => class_exists(LaravelMpdf::class))
            ->action(function (Order $record) {
                // buyer/purchasable are deliberately NOT eager-loaded here (or
                // anywhere in this resource): Eloquent's MorphTo eager loading
                // unconditionally instantiates the morph class, so a single
                // stale/renamed buyer_type or purchasable_type value would
                // throw and break the whole invoice, not just degrade its
                // display. buyerDisplayName()/purchasableDisplayName() guard
                // against that on the lazy path instead (see ResolvesDisplayName).
                $record->loadMissing(['transactions', 'items']);

                $pdf = LaravelMpdf::loadView('purchasable::orders.invoice', ['order' => $record])
                    ->output();

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
            ->label(static::morphFieldLabel($name))
            ->schema([
                Select::make($typeColumn)
                    ->label(static::morphFieldLabel($typeColumn))
                    ->options(fn (): array => static::morphTypeOptions($typeColumn))
                    ->native(false),
                TextInput::make($idColumn)
                    ->label(static::morphFieldLabel($idColumn))
                    ->numeric(),
            ])
            ->query(fn (Builder $query, array $data): Builder => $query
                ->when($data[$typeColumn] ?? null, fn (Builder $query, string $type) => $query->where($typeColumn, $type))
                ->when($data[$idColumn] ?? null, fn (Builder $query, string $id) => $query->where($idColumn, $id)))
            ->indicateUsing(fn (array $data) => array_filter([
                $typeColumn => filled($data[$typeColumn] ?? null) ? static::morphFieldLabel($typeColumn).': '.class_basename($data[$typeColumn]) : null,
                $idColumn => filled($data[$idColumn] ?? null) ? static::morphFieldLabel($idColumn).': '.$data[$idColumn] : null,
            ]));
    }

    protected static function morphFieldLabel(string $column): string
    {
        return match ($column) {
            'buyer' => __('Buyer'),
            'buyer_type' => __('Buyer type'),
            'buyer_id' => __('Buyer ID'),
            'purchasable' => __('Purchasable'),
            'purchasable_type' => __('Purchasable type'),
            'purchasable_id' => __('Purchasable ID'),
        };
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

    /**
     * @return array<class-string>
     */
    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
        ];
    }
}
