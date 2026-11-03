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
#[Name('search-orders')]
// What the model reads to decide whether this tool answers its question.
#[Description('Search orders by order number, customer name/email, or status. Returns a compact list; use get-order for full detail on one order.')]
#[IsReadOnly]
class SearchOrdersTool extends Tool
{
    /**
     * The only code that executes on a call — eloquent in, text out.
     */
    public function handle(Request $request): Response
    {
        $orders = Order::query()
            ->with('customer')
            ->when($request->get('order_number'), fn ($q, $n) => $q->where('number', 'like', "%{$n}%"))
            ->when($request->get('customer'), fn ($q, $c) => $q->whereHas(
                'customer',
                fn ($cq) => $cq->where('name', 'like', "%{$c}%")->orWhere('email', 'like', "%{$c}%")
            ))
            ->when($request->get('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('placed_at')
            ->limit(10)
            ->get();

        if ($orders->isEmpty()) {
            McpAudit::record('search-orders', $request->all(), '0 orders matched');

            return Response::text('No orders matched that search.');
        }

        $lines = $orders->map(fn (Order $o) => sprintf(
            '%s · %s%s · %s · $%.2f · placed %s',
            $o->number,
            $o->customer->name,
            $o->customer->company ? " ({$o->customer->company})" : '',
            $o->status,
            $o->total_cents / 100,
            $o->placed_at?->toDateString() ?? 'unplaced',
        ));

        McpAudit::record('search-orders', $request->all(), $orders->count().' order(s) matched');

        return Response::text($lines->join("\n"));
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
                ->description('Partial or full order number, e.g. "AS-1050" or just "1050".'),
            'customer' => $schema->string()
                ->description('Customer name or email fragment. Names are not unique — prefer email or confirm the company.'),
            'status' => $schema->string()
                ->enum(['pending', 'paid', 'shipped', 'delivered', 'cancelled', 'refunded'])
                ->description('Filter by order status.'),
        ];
    }
}
