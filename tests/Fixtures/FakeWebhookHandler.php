<?php

namespace Amzad\LaravelPurchasable\Tests\Fixtures;

use Amzad\LaravelPurchasable\Contracts\WebhookHandlerContract;
use Illuminate\Http\Request;

class FakeWebhookHandler implements WebhookHandlerContract
{
    public static bool $called = false;

    public static ?array $payload = null;

    public static function reset(): void
    {
        self::$called = false;
        self::$payload = null;
    }

    public function handle(Request $request): void
    {
        self::$called = true;
        self::$payload = $request->all();
    }
}
