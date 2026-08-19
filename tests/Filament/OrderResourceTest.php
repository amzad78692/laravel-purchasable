<?php

use Amzad\LaravelPurchasable\Filament\Exports\OrderExporter;
use Amzad\LaravelPurchasable\Filament\PurchasablePlugin;
use Amzad\LaravelPurchasable\Filament\Resources\OrderResource;
use Amzad\LaravelPurchasable\Filament\Resources\OrderResource\Pages\ListOrders;
use Amzad\LaravelPurchasable\Filament\Resources\OrderResource\Pages\ViewOrder;
use Amzad\LaravelPurchasable\Filament\Resources\OrderResource\RelationManagers\ItemsRelationManager;
use Amzad\LaravelPurchasable\Models\Order;
use Amzad\LaravelPurchasable\Models\OrderItem;
use Amzad\LaravelPurchasable\Models\Transaction;
use Amzad\LaravelPurchasable\Tests\Fixtures\TestBuyer;
use Amzad\LaravelPurchasable\Tests\Fixtures\TestBuyerWithDisplayName;
use Amzad\LaravelPurchasable\Tests\Fixtures\TestPurchasable;
use Amzad\LaravelPurchasable\Tests\Fixtures\TestPurchasableWithDisplayName;
use Filament\Actions\ExportAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\Exports\Models\Export;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Column;
use Filament\Tables\Table;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as Pdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

function makeItemsRelationManager(Order $order): ItemsRelationManager
{
    $relationManager = new ItemsRelationManager;
    $relationManager->ownerRecord = $order;
    $relationManager->pageClass = ViewOrder::class;

    return $relationManager;
}

/**
 * Column::getState() needs a fully mounted Livewire table (it reaches back
 * into the owning page/relation manager's $table property for context),
 * which isn't available when a Table is built standalone in a test. The
 * getStateUsing() closures wired up in OrderResource/ItemsRelationManager
 * are plain `fn (Model $record) => ...` callbacks with no such
 * dependency, so pull the closure out directly and call it.
 */
function columnState(Column $column, Order|OrderItem $record): mixed
{
    $property = new ReflectionProperty($column, 'getStateUsing');
    $property->setAccessible(true);

    return ($property->getValue($column))($record);
}

it('registers the order resource on the panel by default', function () {
    expect(PurchasablePlugin::make()->getResources())->toContain(OrderResource::class);
});

it('excludes the order resource once disabled via the plugin', function () {
    expect(PurchasablePlugin::make()->orders(false)->getResources())->not->toContain(OrderResource::class);
});

it('generates working index and view urls for an order', function () {
    $order = Order::factory()->paid()->create();

    expect(OrderResource::getUrl('index'))->toContain('/admin/orders')
        ->and(OrderResource::getUrl('view', ['record' => $order]))->toContain('/admin/orders/'.$order->getKey());
});

it('reads the navigation group from the plugin configuration', function () {
    expect(OrderResource::getNavigationGroup())->toBe('Purchasable');
});

it('filters orders by buyer type and id', function () {
    $match = Order::factory()->create(['buyer_type' => 'App\\Models\\User', 'buyer_id' => 7]);
    Order::factory()->create(['buyer_type' => 'App\\Models\\User', 'buyer_id' => 8]);
    Order::factory()->create(['buyer_type' => 'App\\Models\\Admin', 'buyer_id' => 7]);

    $filter = OrderResource::table(Table::make(new ListOrders))->getFilters()['buyer'];

    $results = $filter->apply(Order::query(), ['buyer_type' => 'App\\Models\\User', 'buyer_id' => 7])->pluck('id');

    expect($results->all())->toBe([$match->id]);
});

it('filters orders by purchasable type and id', function () {
    $match = Order::factory()->create(['purchasable_type' => 'App\\Models\\Event', 'purchasable_id' => 3]);
    Order::factory()->create(['purchasable_type' => 'App\\Models\\Event', 'purchasable_id' => 4]);
    Order::factory()->create(['purchasable_type' => 'App\\Models\\Plan', 'purchasable_id' => 3]);

    $filter = OrderResource::table(Table::make(new ListOrders))->getFilters()['purchasable'];

    $results = $filter->apply(Order::query(), ['purchasable_type' => 'App\\Models\\Event', 'purchasable_id' => 3])->pluck('id');

    expect($results->all())->toBe([$match->id]);
});

it('filters orders by created_at date range', function () {
    $inRange = Order::factory()->create(['created_at' => '2026-02-15']);
    Order::factory()->create(['created_at' => '2026-01-01']);
    Order::factory()->create(['created_at' => '2026-03-01']);

    $filter = OrderResource::table(Table::make(new ListOrders))->getFilters()['created_at'];

    $results = $filter->apply(Order::query(), ['created_from' => '2026-02-01', 'created_until' => '2026-02-28'])->pluck('id');

    expect($results->all())->toBe([$inRange->id]);
});

it('wires ExportAction into the orders list header actions', function () {
    $method = new ReflectionMethod(ListOrders::class, 'getHeaderActions');
    $method->setAccessible(true);
    $actions = $method->invoke(new ListOrders);

    expect($actions)->toHaveCount(1)
        ->and($actions[0])->toBeInstanceOf(ExportAction::class)
        ->and($actions[0]->getExporter())->toBe(OrderExporter::class);
});

it('wires ExportBulkAction into the orders table toolbar', function () {
    $toolbarActions = OrderResource::table(Table::make(new ListOrders))->getToolbarActions();

    expect($toolbarActions)->toHaveCount(1)
        ->and($toolbarActions[0])->toBeInstanceOf(ExportBulkAction::class)
        ->and($toolbarActions[0]->getExporter())->toBe(OrderExporter::class);
});

it('exports orders with formatted status and morph type columns', function () {
    $order = Order::factory()->paid()->create([
        'buyer_type' => 'App\\Models\\User',
        'buyer_id' => 5,
    ]);

    $exporter = new OrderExporter(new Export, [
        'order_number' => 'order_number',
        'status' => 'status',
        'buyer_type' => 'buyer_type',
    ], []);

    expect($exporter($order))->toBe([$order->order_number, 'Paid', 'User']);
});

it('shows the print invoice action since laravel-mpdf is installed', function () {
    expect(OrderResource::invoiceAction()->isVisible())->toBeTrue();
});

it('renders a downloadable pdf invoice containing the order and its transactions', function () {
    $order = Order::factory()->paid()->create();
    Transaction::factory()->for($order)->create(['gateway' => 'stripe']);

    $html = view('purchasable::orders.invoice', ['order' => $order->load('transactions')])->render();

    expect($html)->toContain($order->order_number)
        ->toContain('stripe');

    $html = mb_convert_encoding($html, 'UTF-8', 'UTF-8');
    $pdf = Pdf::loadHTML($html)->output();

    expect($pdf)->toStartWith('%PDF');
});

it('returns a StreamedResponse from the invoice action so Livewire recognizes it as a file download', function () {
    // Livewire's file-download detection (SupportFileDownloads) only recognizes
    // StreamedResponse/BinaryFileResponse return values from an action. Anything
    // else — including a plain Response — falls through into Livewire's normal
    // JSON response encoding, which then fails trying to json_encode the raw
    // (non-UTF8) PDF binary: "Malformed UTF-8 characters, possibly incorrectly
    // encoded".
    $order = Order::factory()->paid()->create();

    $response = OrderResource::invoiceAction()->getActionFunction()($order);

    expect($response)->toBeInstanceOf(StreamedResponse::class);

    ob_start();
    $response->sendContent();
    $content = ob_get_clean();

    expect($content)->toStartWith('%PDF');
});

it('includes a line-items table in the invoice for a multi-item order', function () {
    $buyer = TestBuyer::create();
    $purchasable = TestPurchasable::create(['price' => '10.000']);
    $order = $buyer->purchaseMultiple([
        ['purchasable' => $purchasable, 'quantity' => 2],
    ]);

    $html = view('purchasable::orders.invoice', ['order' => $order->load(['transactions', 'items'])])->render();

    expect($html)->toContain('Test Purchasable')
        ->toContain('20.00');
});

it('shows a "Billed to" block with the resolved buyer display name in the invoice', function () {
    $buyer = TestBuyerWithDisplayName::create(['name' => 'Jane Doe']);
    $purchasable = TestPurchasable::create(['price' => '10.000']);
    $order = $buyer->purchase($purchasable);

    $html = view('purchasable::orders.invoice', ['order' => $order->load(['transactions', 'items'])])->render();

    expect($html)->toContain('Billed to')
        ->toContain('Jane Doe');
});

it('shows a purchasable line for a single-item order in the invoice, which previously showed nothing', function () {
    $buyer = TestBuyer::create();
    $purchasable = TestPurchasableWithDisplayName::create(['price' => '10.000', 'name' => 'VIP Ticket']);
    $order = $buyer->purchase($purchasable);

    $html = view('purchasable::orders.invoice', ['order' => $order->load(['transactions', 'items'])])->render();

    expect($html)->toContain('VIP Ticket');
});

it('shows the resolved buyer and purchasable display names in the orders table', function () {
    $buyer = TestBuyerWithDisplayName::create(['name' => 'Jane Doe']);
    $purchasable = TestPurchasableWithDisplayName::create(['price' => '10.000', 'name' => 'VIP Ticket']);
    $order = $buyer->purchase($purchasable);

    $columns = OrderResource::table(Table::make(new ListOrders))->getColumns();

    $buyerColumn = collect($columns)->first(fn ($column) => $column->getName() === 'buyer_display');
    $purchasableColumn = collect($columns)->first(fn ($column) => $column->getName() === 'purchasable_display');

    expect(columnState($buyerColumn, $order))->toBe('Jane Doe')
        ->and(columnState($purchasableColumn, $order))->toBe('VIP Ticket');
});

it('shows the resolved buyer and purchasable display names in the order infolist', function () {
    $buyer = TestBuyerWithDisplayName::create(['name' => 'Jane Doe']);
    $purchasable = TestPurchasableWithDisplayName::create(['price' => '10.000', 'name' => 'VIP Ticket']);
    $order = $buyer->purchase($purchasable);

    $schema = OrderResource::infolist(Schema::make(new ViewOrder))->record($order);

    $buyerEntry = $schema->getComponent(fn ($component) => $component instanceof TextEntry && $component->getName() === 'buyer_display');
    $purchasableEntry = $schema->getComponent(fn ($component) => $component instanceof TextEntry && $component->getName() === 'purchasable_display');

    expect($buyerEntry->getState())->toBe('Jane Doe')
        ->and($purchasableEntry->getState())->toBe('VIP Ticket');
});

it('hides the purchasable infolist entry for a multi-item order', function () {
    $buyer = TestBuyer::create();
    $purchasable = TestPurchasable::create(['price' => '10.000']);
    $order = $buyer->purchaseMultiple([
        ['purchasable' => $purchasable, 'quantity' => 1],
    ]);

    $schema = OrderResource::infolist(Schema::make(new ViewOrder))->record($order);

    $purchasableEntry = $schema->getComponent(fn ($component) => $component instanceof TextEntry && $component->getName() === 'purchasable_display');

    expect($purchasableEntry)->toBeNull();
});

it('sanitizes invalid UTF-8 bytes before handing the invoice HTML to mpdf', function () {
    // Real-world gateway values occasionally contain invalid UTF-8 byte sequences
    // (e.g. a corrupted webhook payload). mPDF's loadHTML() rejects those
    // outright with malformed-UTF-8 errors, so the invoice action must
    // normalize the rendered HTML before handing it to mpdf.
    $order = Order::factory()->paid()->create();
    Transaction::factory()->for($order)->create(['gateway' => "stripe\xC3\x28"]);

    $html = view('purchasable::orders.invoice', ['order' => $order->load('transactions')])->render();
    $html = mb_convert_encoding($html, 'UTF-8', 'UTF-8');

    $pdf = Pdf::loadHTML($html)->output();

    expect($pdf)->toStartWith('%PDF');
});

it('safely renders order meta mixing scalar and nested values', function () {
    // meta mixes a top-level scalar ('seat_tier') with a nested array ('add_ons').
    // TextEntry treats array state as a list and calls formatState() once per
    // top-level value, so a naive formatStateUsing(?array) callback blows up on
    // the scalar entry ("string given" / array-to-string).
    $order = Order::factory()->create([
        'meta' => [
            'seat_tier' => 'VIP',
            'add_ons' => ['id' => 'parking', 'price' => '10.000'],
        ],
    ]);

    $schema = OrderResource::infolist(Schema::make(new ViewOrder))->record($order);

    $entry = $schema->getComponent(
        fn ($component) => $component instanceof TextEntry && $component->getName() === 'meta'
    );

    expect($entry)->not->toBeNull();

    $state = $entry->getState();

    expect($state)->toBeString()
        ->and($state)->toContain('"seat_tier"');

    expect(fn () => $entry->toEmbeddedHtml())->not->toThrow(Throwable::class);
});

it('hides the items relation manager for a single-item order', function () {
    $order = Order::factory()->paid()->create();

    expect(ItemsRelationManager::canViewForRecord($order, ViewOrder::class))->toBeFalse();
});

it('shows the items relation manager, listing each line item, for a multi-item order', function () {
    $buyer = TestBuyer::create();
    $purchasable = TestPurchasable::create(['price' => '10.000']);
    $order = $buyer->purchaseMultiple([
        ['purchasable' => $purchasable, 'quantity' => 3],
    ]);

    expect(ItemsRelationManager::canViewForRecord($order, ViewOrder::class))->toBeTrue();

    $relationManager = makeItemsRelationManager($order);

    expect($relationManager->getRelationship()->get()->pluck('id')->sort()->values())
        ->toEqual($order->items->pluck('id')->sort()->values());
});

it('renders the items relation manager using Filament\'s built-in Table component, not an infolist entry', function () {
    $order = Order::factory()->create();

    $relationManager = makeItemsRelationManager($order);
    $table = $relationManager->table(Table::make($relationManager));

    expect($table)->toBeInstanceOf(Table::class);

    $columnNames = collect($table->getColumns())->map->getName()->values()->all();

    expect($columnNames)->toBe(['purchasable_display', 'purchasable_type', 'purchasable_id', 'quantity', 'unit_price', 'amount', 'status']);
});

it('shows the resolved purchasable display name as a relation manager table column', function () {
    $buyer = TestBuyer::create();
    $purchasable = TestPurchasableWithDisplayName::create(['price' => '10.000', 'name' => 'VIP Ticket']);
    $order = $buyer->purchaseMultiple([
        ['purchasable' => $purchasable, 'quantity' => 1],
    ]);

    $relationManager = makeItemsRelationManager($order);
    $table = $relationManager->table(Table::make($relationManager));

    $column = collect($table->getColumns())->first(fn ($column) => $column->getName() === 'purchasable_display');
    $item = $order->items()->first();

    expect(columnState($column, $item))->toBe('VIP Ticket');
});
