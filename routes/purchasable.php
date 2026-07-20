<?php

use Amzad\LaravelPurchasable\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/purchasable/webhooks/{gateway}', WebhookController::class)
    ->name('purchasable.webhooks');
