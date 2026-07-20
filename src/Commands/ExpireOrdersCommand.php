<?php

namespace Amzad\LaravelPurchasable\Commands;

use Amzad\LaravelPurchasable\Actions\CancelOrder;
use Amzad\LaravelPurchasable\Models\Order;
use Illuminate\Console\Command;

class ExpireOrdersCommand extends Command
{
    public $signature = 'purchasable:expire-orders';

    public $description = 'Cancel pending orders older than the configured pending_order_ttl';

    public function handle(CancelOrder $cancelOrder): int
    {
        $ttlMinutes = config('purchasable.pending_order_ttl');

        if ($ttlMinutes === null) {
            $this->comment('purchasable.pending_order_ttl is not set — nothing to expire.');

            return self::SUCCESS;
        }

        $staleOrders = Order::query()
            ->pending()
            ->where('created_at', '<=', now()->subMinutes((int) $ttlMinutes))
            ->get();

        foreach ($staleOrders as $order) {
            $cancelOrder->execute($order);
        }

        $this->info("Expired {$staleOrders->count()} pending order(s).");

        return self::SUCCESS;
    }
}
