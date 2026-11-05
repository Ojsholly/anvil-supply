<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\McpAudit;
use App\Models\Order;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

// Lookup key — tools/call addresses the tool by this string.
#[Name('get-order')]
// What the model reads to decide whether this tool answers its question.
#[Description('Get the full detail of one order by order number: customer, line items, totals, status, shipping date, and any refunds issued.')]
#[IsReadOnly]
class GetOrderTool extends Tool
{
    /**
     * The only code that executes on a call — eloquent in, text out.
     */
    public function handle(Request $request): Response
    {
        $order = Order::with(['customer', 'items.product', 'refunds'])
            ->where('number', $request->string('order_number'))
            ->first();

        if (! $order) {
            McpAudit::record('get-order', $request->all(), "not found: {$request->string('order_number')}");

            return Response::text("No order found for number {$request->string('order_number')}. Check the format — order numbers look like AS-1044.");
        }

        $lines = [
            "Order {$order->number}",
            "Customer: {$order->customer->name} <{$order->customer->email}>".
                ($order->customer->company ? " — {$order->customer->company}" : ''),
            "Status: {$order->status}",
            'Placed: '.($order->placed_at?->toDateTimeString() ?? '—'),
            'Shipped: '.($order->shipped_at?->toDateString() ?? '—'),
            'Items:',
        ];

        foreach ($order->items as $item) {
            $lines[] = sprintf(
                '  - %s × %d @ $%.2f — %s',
                $item->product->sku,
                $item->quantity,
                $item->unit_price_cents / 100,
                $item->product->name,
            );
        }

        $lines[] = sprintf('Total: $%.2f', $order->total_cents / 100);

        foreach ($order->refunds as $refund) {
            $lines[] = sprintf(
                'Refund: $%.2f (%s) — %s',
                $refund->amount_cents / 100,
                $refund->processed_at?->toDateString() ?? 'pending',
                $refund->reason ?? 'no reason recorded',
            );
        }

        McpAudit::record('get-order', $request->all(), "order {$order->number} returned");

        return Response::text(implode("\n", $lines));
    }

    /**
     * Becomes the tool's inputSchema — arguments are validated
     * against this before handle() runs.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'order_number' => $schema->string()
                ->description('Full order number, e.g. "AS-1050".')
                ->required(),
        ];
    }
}
