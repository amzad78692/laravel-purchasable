<?php

namespace Amzad\LaravelPurchasable\Contracts;

use Illuminate\Http\Request;

/**
 * Implemented per-gateway by the host app (or a gateway adapter package).
 * Signature verification is this implementation's responsibility — the
 * package's WebhookController only routes the raw request here, it has
 * no gateway-specific knowledge of its own.
 */
interface WebhookHandlerContract
{
    public function handle(Request $request): void;
}
