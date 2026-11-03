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
    // Server-side hard cap per call — the client confirm-click is a
    // courtesy; this check is the boundary.
    public const MAX_REFUND_CENTS = 50000; // $500

    /**
     * The only code that executes on a call — eloquent in, text out.
     * Every refusal path returns Response::error() so the model
     * reads it as a failed call, not a successful answer.
     */
    public function handle(Request $request): Response
    {
        $orderNumber = (string) $request->string('order_number');
        $order = Order::with('refunds')->where('number', $orderNumber)->first();

        if (! $order) {
            return Response::error("No order found for number {$orderNumber}.");
        }

        // A repeat call carrying a key the client already used replays the
        // stored refund instead of charging the customer a second time.
        $key = (string) $request->string('idempotency_key');
        if ($key !== '') {
            $existing = $order->refunds->firstWhere('idempotency_key', $key);
            if ($existing) {
                return Response::text(sprintf(
                    'Idempotent replay — refund #%d for $%.2f on %s was already recorded. No second charge.',
                    $existing->id,
                    $existing->amount_cents / 100,
                    $orderNumber
                ));
            }
        }

        // Status guardrails — what the server itself refuses.
        if ($order->status === 'cancelled') {
            return Response::error("Order {$orderNumber} is cancelled — it was never charged, nothing to refund.");
        }
        if ($order->status === 'pending') {
            return Response::error("Order {$orderNumber} is unpaid (status: pending) — nothing has been charged.");
        }

        $refundedCents = $order->refunds->sum('amount_cents');
        $remainingCents = $order->total_cents - $refundedCents;
        if ($remainingCents <= 0) {
            return Response::error("Order {$orderNumber} is already fully refunded — double refunds are refused.");
        }

        $amountCents = $request->integer('amount_cents') ?: $remainingCents;
        if ($amountCents <= 0) {
            return Response::error('Refund amount must be a positive number of cents.');
        }
        if ($amountCents > self::MAX_REFUND_CENTS) {
            return Response::error(sprintf(
                'Refund exceeds the $%.2f per-call cap — larger refunds need a human operator.',
                self::MAX_REFUND_CENTS / 100
            ));
        }
        if ($amountCents > $remainingCents) {
            return Response::error(sprintf(
                'Only $%.2f is still refundable on %s — refused to refund $%.2f.',
                $remainingCents / 100,
                $orderNumber,
                $amountCents / 100
            ));
        }

        $refund = $order->refunds()->create([
            'amount_cents' => $amountCents,
            'reason' => $request->string('reason') ?: null,
            'idempotency_key' => $key !== '' ? $key : 'mcp-'.bin2hex(random_bytes(8)),
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
