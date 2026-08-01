<?php

namespace Amzad\LaravelPurchasable\Filament;

use Amzad\LaravelPurchasable\Filament\Resources\OrderResource;
use Amzad\LaravelPurchasable\Filament\Resources\TransactionResource;
use Filament\Contracts\Plugin;
use Filament\Panel;

/**
 * Optional Filament v4 integration. Not registered automatically — enable it
 * from the host app's Panel provider:
 *
 *     ->plugin(PurchasablePlugin::make())
 *
 * Both resources are read-only (list + view, no create/edit/delete) and can
 * be toggled or reassigned a navigation group independently.
 */
class PurchasablePlugin implements Plugin
{
    protected bool $hasOrderResource = true;

    protected bool $hasTransactionResource = true;

    protected ?string $navigationGroup = null;

    protected bool $navigationGroupWasSet = false;

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static */
        return filament(app(static::class)->getId());
    }

    public function getId(): string
    {
        return 'purchasable';
    }

    public function register(Panel $panel): void
    {
        $panel->resources($this->getResources());
    }

    public function boot(Panel $panel): void
    {
        //
    }

    /**
     * @return array<class-string>
     */
    public function getResources(): array
    {
        return [
            ...($this->hasOrderResource ? [OrderResource::class] : []),
            ...($this->hasTransactionResource ? [TransactionResource::class] : []),
        ];
    }

    public function orders(bool $condition = true): static
    {
        $this->hasOrderResource = $condition;

        return $this;
    }

    public function transactions(bool $condition = true): static
    {
        $this->hasTransactionResource = $condition;

        return $this;
    }

    public function navigationGroup(?string $group): static
    {
        $this->navigationGroup = $group;
        $this->navigationGroupWasSet = true;

        return $this;
    }

    public function getNavigationGroup(): ?string
    {
        if (! $this->navigationGroupWasSet) {
            return __('Purchasable');
        }

        return $this->navigationGroup;
    }
}
