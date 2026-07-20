<?php

namespace Amzad\LaravelPurchasable\Support;

use Illuminate\Support\Facades\DB;

class OrderNumberGenerator
{
    /**
     * @param  string  $configKey  'order_number' or 'transaction_number' (see config/purchasable.php)
     */
    public function generate(string $configKey): string
    {
        $format = (string) config("purchasable.{$configKey}.format");
        $year = now()->year;

        $scope = $configKey.(str_contains($format, '{year}') ? ":{$year}" : '');
        $format = str_replace('{year}', (string) $year, $format);

        return (string) preg_replace_callback(
            '/\{sequence:(\d+)\}/',
            fn (array $matches) => str_pad((string) $this->nextSequence($scope), (int) $matches[1], '0', STR_PAD_LEFT),
            $format,
        );
    }

    /**
     * Concurrency-safe increment: locks the counter row for the duration of
     * the transaction so parallel order/transaction creation can't collide.
     */
    protected function nextSequence(string $scope): int
    {
        return DB::transaction(function () use ($scope) {
            DB::table('purchasable_sequences')->insertOrIgnore([
                'key' => $scope,
                'value' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $row = DB::table('purchasable_sequences')->where('key', $scope)->lockForUpdate()->first();

            $next = $row->value + 1;

            DB::table('purchasable_sequences')->where('key', $scope)->update([
                'value' => $next,
                'updated_at' => now(),
            ]);

            return $next;
        });
    }
}
