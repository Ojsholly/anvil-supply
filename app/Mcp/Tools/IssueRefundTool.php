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
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

// Lookup key — tools/call addresses the tool by this string.
#[Name('issue-refund')]
// Executes ONLY human-approved refund requests — the agent can draft
// (request-refund) and execute, but cannot approve.
#[Description('Execute a refund that a human operator has approved via `php artisan refund:approve`. Refuses if no approved request exists for the order — approvals are out-of-band, not arguments.')]
// Marks the tool destructive — advisory metadata; clients may ignore it.
#[IsDestructive]
class IssueRefundTool extends Tool
{
    /**
     * The only code that executes on a call — eloquent in, text out.
     * The boundary is the approved_at timestamp: no human signature,
     * no money movement — and that field can't be set through MCP.
     */
    public function handle(Request $request): Response
    {
        $request->validate(['order_number' => 'required|string']);

        $orderNumber = (string) $request->string('order_number');
        $order = Order::with('refunds')->where('number', $orderNumber)->first();

        if (! $order) {
            return $this->deny($orderNumber, $request->all(), "No order found for number {$orderNumber}.");
        }

        // The executor needs an approved request. Pending ≠ approved —
        // a human's approved_at timestamp is the difference.
        $pending = $order->refunds->where('status', 'pending')->first();
        $approved = $order->refunds->where('status', 'approved')->first();

        if (! $approved) {
            if ($pending) {
                return $this->deny($orderNumber, $request->all(), "Refund request #{$pending->id} for {$orderNumber} is still pending — no human has approved it. Run `php artisan refund:approve {$pending->id}` first.");
            }

            // A re-call after the request already processed replays the
            // record — the executed row IS the idempotency record.
            $processed = $order->refunds->where('status', 'processed')->sortByDesc('processed_at')->first();
            if ($processed) {
                McpAudit::record('issue-refund', $request->all(), "idempotent replay — refund #{$processed->id} already processed");

                return Response::text(sprintf(
                    'Idempotent replay — refund #%d for $%.2f on %s was already processed. To refund more, draft a new request with request-refund and have a human approve it.',
                    $processed->id, $processed->amount_cents / 100, $orderNumber
                ));
            }

            return $this->deny($orderNumber, $request->all(), "No approved refund request exists for {$orderNumber}. Draft one with request-refund, then a human approves it out-of-band.");
        }

        $approved->update([
            'status' => 'processed',
            'processed_at' => now(),
        ]);

        $processedCents = $order->refunds->where('status', 'processed')->sum('amount_cents');
        if ($processedCents >= $order->total_cents) {
            $order->update(['status' => 'refunded']);
        }

        McpAudit::record('issue-refund', $request->all(), sprintf(
            'refunded $%.2f on %s (refund #%d, approved by %s)',
            $approved->amount_cents / 100, $orderNumber, $approved->id, $approved->approved_by
        ));

        return Response::text(sprintf(
            'Refund $%.2f processed on %s (refund #%d, approved by %s). %s',
            $approved->amount_cents / 100,
            $orderNumber,
            $approved->id,
            $approved->approved_by,
            $order->status === 'refunded' ? 'Order fully refunded.' : 'Partial refund — order status unchanged.'
        ));
    }

    // Refusals are audited too — an unapproved execution attempt
    // leaves a row, not silence.
    protected function deny(string $orderNumber, array $arguments, string $message): Response
    {
        McpAudit::record('issue-refund', $arguments, 'refused: '.$message);

        return Response::error($message);
    }

    /**
     * Becomes the tool's inputSchema — amount is intentionally absent:
     * the executor can't set it, only the approved request carries it.
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
