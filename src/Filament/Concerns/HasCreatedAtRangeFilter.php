<?php

namespace Amzad\LaravelPurchasable\Filament\Concerns;

use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

trait HasCreatedAtRangeFilter
{
    protected static function createdAtRangeFilter(): Filter
    {
        return Filter::make('created_at')
            ->label('Date range')
            ->schema([
                DatePicker::make('created_from')
                    ->label('Created from')
                    ->native(false),
                DatePicker::make('created_until')
                    ->label('Created until')
                    ->native(false),
            ])
            ->query(fn (Builder $query, array $data): Builder => $query
                ->when($data['created_from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date))
                ->when($data['created_until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date)))
            ->indicateUsing(fn (array $data): array => array_filter([
                'created_from' => filled($data['created_from'] ?? null) ? 'Created from '.$data['created_from'] : null,
                'created_until' => filled($data['created_until'] ?? null) ? 'Created until '.$data['created_until'] : null,
            ]));
    }
}
