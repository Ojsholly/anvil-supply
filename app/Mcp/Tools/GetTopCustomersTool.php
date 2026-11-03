<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\McpAudit;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

// Lookup key — tools/call addresses the tool by this string.
#[Name('get-top-customers')]
// What the model reads to decide whether this tool answers its question.
#[Description('Rank customers by total spend. Returns order count, gross spend (all orders), and net spend (excludes cancelled and refunded orders) per customer. Use for leaderboard, "top customer", or spend-ranking questions.')]
#[IsReadOnly]
class GetTopCustomersTool extends Tool
{
    /**
     * The only code that executes on a call — eloquent in, text out.
     */
    public function handle(Request $request): Response
    {
        $limit = max(1, min(50, (int) ($request->get('limit') ?? 10)));

        $rows = DB::table('customers')
            ->join('orders', 'orders.customer_id', '=', 'customers.id')
            ->groupBy('customers.id', 'customers.name', 'customers.company', 'customers.email')
            ->select(
                'customers.name',
                'customers.company',
                'customers.email',
                DB::raw('COUNT(orders.id) AS order_count'),
                DB::raw('SUM(orders.total_cents) AS gross_cents'),
                DB::raw("SUM(CASE WHEN orders.status NOT IN ('cancelled','refunded') THEN orders.total_cents ELSE 0 END) AS net_cents"),
                DB::raw("SUM(orders.status = 'cancelled') AS cancelled_count"),
                DB::raw("SUM(orders.status = 'refunded') AS refunded_count")
            )
            ->orderByDesc('gross_cents')
            ->orderByDesc('order_count')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            McpAudit::record('get-top-customers', $request->all(), '0 customers with orders');

            McpAudit::record('get-top-customers', $request->all(), $rows->count().' customer(s) ranked');

            return Response::text('No customers with orders found.');
        }

        $lines = $rows->values()->map(fn ($r, $i) => sprintf(
            '#%d %s%s <%s> — %d order(s) · gross $%.2f · net $%.2f%s',
            $i + 1,
            $r->name,
            $r->company ? " ({$r->company})" : '',
            $r->email,
            $r->order_count,
            $r->gross_cents / 100,
            $r->net_cents / 100,
            ($r->cancelled_count || $r->refunded_count)
                ? sprintf(' · excl. %d cancelled, %d refunded', $r->cancelled_count, $r->refunded_count)
                : ''
        ));

        return Response::text(
            "Customers ranked by gross spend (net excludes cancelled + refunded):\n".$lines->join("\n")
        );
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
            'limit' => $schema->integer()
                ->description('Max customers to return (default 10).'),
        ];
    }
}
