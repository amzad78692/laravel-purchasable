<?php

use Amzad\LaravelPurchasable\Tests\Fixtures\FakeWebhookHandler;

beforeEach(fn () => FakeWebhookHandler::reset());

it('routes a webhook to the handler registered for the gateway', function () {
    config()->set('purchasable.webhook_handlers.fake', FakeWebhookHandler::class);

    $response = $this->postJson('/purchasable/webhooks/fake', ['event' => 'charge.succeeded']);

    $response->assertNoContent();
    expect(FakeWebhookHandler::$called)->toBeTrue()
        ->and(FakeWebhookHandler::$payload)->toBe(['event' => 'charge.succeeded']);
});

it('returns 404 for a gateway with no registered handler', function () {
    config()->set('purchasable.webhook_handlers', []);

    $this->postJson('/purchasable/webhooks/unregistered', [])->assertNotFound();
});
