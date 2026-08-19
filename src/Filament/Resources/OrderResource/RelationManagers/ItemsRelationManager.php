<?php

namespace Amzad\LaravelPurchasable\Filament\Resources\OrderResource\RelationManagers;

use Amzad\LaravelPurchasable\Enums\OrderItemStatus;
use Amzad\LaravelPurchasable\Models\Order;
use Amzad\LaravelPurchasable\Models\OrderItem;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only line-item listing for a multi-item order — matches the rest of
 * this package's Filament integration (list + view only, no create/edit/
 * delete; items are only ever created via CreateMultiItemOrder). Hidden
 * entirely for single-item orders, which don't have any OrderItem rows.
 */
class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Items');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        /** @var Order $ownerRecord */
        return $ownerRecord->items()->exists();
    }

    public function table(Table $table): Table
    {
        return $table
            // purchasable is deliberately not eager-loaded: MorphTo eager
            // loading unconditionally instantiates the morph class, so one
            // stale/renamed purchasable_type among the items would throw
            // instead of degrading gracefully. See ResolvesDisplayName.
            ->columns([
                TextColumn::make('purchasable_display')
                    ->label(__('Purchasable'))
                    ->getStateUsing(fn (OrderItem $record): string => $record->purchasableDisplayName()),
                TextColumn::make('purchasable_type')
                    ->label(__('Purchasable type'))
                    ->formatStateUsing(fn (string $state): string => class_basename($state))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('purchasable_id')
                    ->label(__('Purchasable ID'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('quantity')
                    ->label(__('Quantity')),
                TextColumn::make('unit_price')
                    ->label(__('Unit price'))
                    ->money(fn (OrderItem $record): string => $record->currency),
                TextColumn::make('amount')
                    ->label(__('Amount'))
                    ->money(fn (OrderItem $record): string => $record->currency),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->formatStateUsing(fn (OrderItemStatus $state): string => $state->label())
                    ->color(fn (OrderItemStatus $state): string => $state === OrderItemStatus::Cancelled ? 'danger' : 'success'),
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([])
            ->defaultSort('id');
    }
}
