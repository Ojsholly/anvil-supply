<?php

namespace App\Mcp\Tools;

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
// What the model reads to decide whether this tool answers its question.
#[Description('Issue a refund for an order by order number. Optionally takes amount_cents (defaults to the remaining refundable balance), a reason, and an idempotency_key to make repeat calls safe.')]
// Marks the tool destructive — clients should confirm with a human before calling.
#[IsDestructive]
class IssueRefundTool extends Tool
{
    /**
     * The only code that executes on a call — eloquent in, text out.
     */
    public function handle(Request $request): Response
    {
        $order = Order::with('refunds')
            ->where('number', $request->string('order_number'))
            ->first();

        if (! $order) {
            return Response::text("No order found for number {$request->string('order_number')}.");
        }

        $refundedCents = $order->refunds->sum('amount_cents');
        $remainingCents = $order->total_cents - $refundedCents;
        $amountCents = $request->integer('amount_cents') ?: $remainingCents;

        $refund = $order->refunds()->create([
            'amount_cents' => $amountCents,
            'reason' => $request->string('reason') ?: null,
            'idempotency_key' => 'mcp-'.bin2hex(random_bytes(8)),
            'status' => 'processed',
            'processed_at' => now(),
        ]);

        if ($refundedCents + $amountCents >= $order->total_cents) {
            $order->update(['status' => 'refunded']);
        }

        return Response::text(sprintf(
            'Refund $%.2f recorded on %s (refund #%d). %s',
            $amountCents / 100,
            $order->number,
            $refund->id,
            $order->status === 'refunded' ? 'Order fully refunded.' : 'Partial refund — order status unchanged.'
        ));
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
            'amount_cents' => $schema->integer()
                ->description('Refund amount in cents. Omit to refund the remaining refundable balance.'),
            'reason' => $schema->string()
                ->description('Reason recorded on the refund, e.g. "damaged on arrival".'),
            'idempotency_key' => $schema->string()
                ->description('Optional client key — repeat calls with the same key are safe.'),
        ];
    }
}
