<?php

use Amzad\LaravelPurchasable\Filament\Exports\TransactionExporter;
use Amzad\LaravelPurchasable\Filament\PurchasablePlugin;
use Amzad\LaravelPurchasable\Filament\Resources\OrderResource;
use Amzad\LaravelPurchasable\Filament\Resources\TransactionResource;
use Amzad\LaravelPurchasable\Filament\Resources\TransactionResource\Pages\ListTransactions;
use Amzad\LaravelPurchasable\Filament\Resources\TransactionResource\Pages\ViewTransaction;
use Amzad\LaravelPurchasable\Models\Transaction;
use Filament\Actions\ExportAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\Exports\Models\Export;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

it('registers the transaction resource on the panel by default', function () {
    expect(PurchasablePlugin::make()->getResources())->toContain(TransactionResource::class);
});

it('excludes the transaction resource once disabled via the plugin', function () {
    expect(PurchasablePlugin::make()->transactions(false)->getResources())->not->toContain(TransactionResource::class);
});

it('generates working index and view urls for a transaction, linked back to its order', function () {
    $transaction = Transaction::factory()->refund()->create();

    expect(TransactionResource::getUrl('index'))->toContain('/admin/transactions')
        ->and(TransactionResource::getUrl('view', ['record' => $transaction]))
        ->toContain('/admin/transactions/'.$transaction->getKey())
        ->and(OrderResource::getUrl('view', ['record' => $transaction->order]))
        ->toContain('/admin/orders/'.$transaction->order_id);
});

it('reads the navigation group from the plugin configuration', function () {
    expect(TransactionResource::getNavigationGroup())->toBe('Purchasable');
});

it('filters transactions by gateway', function () {
    $match = Transaction::factory()->create(['gateway' => 'stripe']);
    Transaction::factory()->create(['gateway' => 'manual']);

    $filter = TransactionResource::table(Table::make(new ListTransactions))->getFilters()['gateway'];

    $results = $filter->apply(Transaction::query(), ['value' => 'stripe'])->pluck('id');

    expect($results->all())->toBe([$match->id]);
});

it('filters transactions by currency', function () {
    $match = Transaction::factory()->create(['currency' => 'KWD']);
    Transaction::factory()->create(['currency' => 'USD']);

    $filter = TransactionResource::table(Table::make(new ListTransactions))->getFilters()['currency'];

    $results = $filter->apply(Transaction::query(), ['value' => 'KWD'])->pluck('id');

    expect($results->all())->toBe([$match->id]);
});

it('filters transactions by order via the order_number relationship', function () {
    $filter = TransactionResource::table(Table::make(new ListTransactions))->getFilters()['order'];

    expect($filter->queriesRelationships())->toBeTrue()
        ->and($filter->getRelationshipName())->toBe('order')
        ->and($filter->getRelationshipTitleAttribute())->toBe('order_number');
});

it('filters transactions by created_at date range', function () {
    $inRange = Transaction::factory()->create(['created_at' => '2026-02-15']);
    Transaction::factory()->create(['created_at' => '2026-01-01']);
    Transaction::factory()->create(['created_at' => '2026-03-01']);

    $filter = TransactionResource::table(Table::make(new ListTransactions))->getFilters()['created_at'];

    $results = $filter->apply(Transaction::query(), ['created_from' => '2026-02-01', 'created_until' => '2026-02-28'])->pluck('id');

    expect($results->all())->toBe([$inRange->id]);
});

it('wires ExportAction into the transactions list header actions', function () {
    $method = new ReflectionMethod(ListTransactions::class, 'getHeaderActions');
    $method->setAccessible(true);
    $actions = $method->invoke(new ListTransactions);

    expect($actions)->toHaveCount(1)
        ->and($actions[0])->toBeInstanceOf(ExportAction::class)
        ->and($actions[0]->getExporter())->toBe(TransactionExporter::class);
});

it('wires ExportBulkAction into the transactions table toolbar', function () {
    $toolbarActions = TransactionResource::table(Table::make(new ListTransactions))->getToolbarActions();

    expect($toolbarActions)->toHaveCount(1)
        ->and($toolbarActions[0])->toBeInstanceOf(ExportBulkAction::class)
        ->and($toolbarActions[0]->getExporter())->toBe(TransactionExporter::class);
});

it('exports transactions with the related order number and formatted enum columns', function () {
    $transaction = Transaction::factory()->refund()->create();

    $exporter = new TransactionExporter(new Export, [
        'transaction_number' => 'transaction_number',
        'order.order_number' => 'order.order_number',
        'type' => 'type',
    ], []);

    expect($exporter($transaction))->toBe([
        $transaction->transaction_number,
        $transaction->order->order_number,
        'Refund',
    ]);
});

it('safely renders a gateway response payload mixing nested arrays and scalar values', function () {
    // A realistic gateway payload (e.g. Stripe) mixes top-level scalars (int, string)
    // with nested arrays. TextEntry treats array state as a list and calls
    // formatState() once per top-level value, so a naive formatStateUsing(?array)
    // callback blows up on the scalar entries ("int given" / array-to-string).
    $transaction = Transaction::factory()->create([
        'response_payload' => [
            'id' => 'evt_1',
            'amount' => 500,
            'customer' => ['id' => 1, 'name' => 'Bob'],
        ],
    ]);

    $schema = TransactionResource::infolist(Schema::make(new ViewTransaction))->record($transaction);

    $entry = $schema->getComponent(
        fn ($component) => $component instanceof TextEntry && $component->getName() === 'response_payload'
    );

    expect($entry)->not->toBeNull();

    $state = $entry->getState();

    expect($state)->toBeString()
        ->and($state)->toContain('"customer"');

    expect(fn () => $entry->toEmbeddedHtml())->not->toThrow(Throwable::class);
});

it('sanitizes an invalid UTF-8 gateway value so Livewire can safely json_encode the page state', function () {
    // Livewire wraps the whole page's rendered state into one JsonResponse on
    // every interaction, with no JSON_INVALID_UTF8_SUBSTITUTE protection — a
    // single invalid UTF-8 byte anywhere breaks that entire response, not just
    // the field it's in. Table/infolist columns for gateway-supplied strings
    // must therefore always produce valid UTF-8 output.
    $bad = "stripe\xC3\x28";

    $tableColumn = TransactionResource::table(Table::make(new ListTransactions))->getColumns()['gateway'];

    $infolistEntry = TransactionResource::infolist(Schema::make(new ViewTransaction))
        ->getComponent(fn ($component) => $component instanceof TextEntry && $component->getName() === 'gateway');

    $tableState = $tableColumn->formatState($bad);
    $infolistState = $infolistEntry->formatState($bad);

    expect(mb_check_encoding($tableState, 'UTF-8'))->toBeTrue()
        ->and(mb_check_encoding($infolistState, 'UTF-8'))->toBeTrue()
        ->and(fn () => json_encode(['gateway' => $tableState], JSON_THROW_ON_ERROR))->not->toThrow(JsonException::class)
        ->and(fn () => json_encode(['gateway' => $infolistState], JSON_THROW_ON_ERROR))->not->toThrow(JsonException::class);
});

it('sanitizes invalid UTF-8 gateway values out of the filter dropdown options', function () {
    Transaction::factory()->create(['gateway' => "stripe\xC3\x28"]);

    $filter = TransactionResource::table(Table::make(new ListTransactions))->getFilters()['gateway'];

    $options = collect($filter->getOptions())->keys()->merge($filter->getOptions());

    $options->each(fn (string $value) => expect(mb_check_encoding($value, 'UTF-8'))->toBeTrue());

    expect(fn () => json_encode($filter->getOptions(), JSON_THROW_ON_ERROR))->not->toThrow(JsonException::class);
});
