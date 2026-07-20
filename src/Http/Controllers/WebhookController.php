<?php

namespace Amzad\LaravelPurchasable\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Generic, gateway-agnostic webhook endpoint. It does no verification or
 * parsing itself — it just resolves the WebhookHandlerContract bound for
 * the {gateway} route segment (config('purchasable.webhook_handlers')) and
 * hands the request to it.
 */
class WebhookController
{
    public function __invoke(Request $request, string $gateway): Response
    {
        $handlerClass = config("purchasable.webhook_handlers.{$gateway}");

        if ($handlerClass === null) {
            throw new NotFoundHttpException("No webhook handler registered for gateway [{$gateway}].");
        }

        app($handlerClass)->handle($request);

        return response()->noContent();
    }
}
