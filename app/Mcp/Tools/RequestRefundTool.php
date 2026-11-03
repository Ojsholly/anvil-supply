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
#[Name('request-refund')]
// Drafts a refund for human review — writes a 'pending' row, moves no money.
#[Description('Draft a refund request for an order. Creates a pending request a human operator must approve with `php artisan refund:approve {id}` before issue-refund can execute it. The drafting step moves no money.')]
// Still marked destructive — it writes state, just not money-moving state.
#[IsDestructive]
class RequestRefundTool extends Tool
{
    public function handle(Request $request): Response
    {
        // Shape validation is the documented idiom; business rules below
        // can't be expressed as validation rules.
        $request->validate([
            'order_number' => 'required|string',
            'amount_cents' => 'nullable|integer|min:1',
            'reason' => 'nullable|string|max:500',
        ]);

        $orderNumber = (string) $request->string('order_number');
        $order = Order::with('refunds')->where('number', $orderNumber)->first();

        if (! $order) {
            return $this->deny($orderNumber, $request->all(), "No order found for number {$orderNumber}.");
        }
        if ($order->status === 'cancelled') {
            return $this->deny($orderNumber, $request->all(), "Order {$orderNumber} is cancelled — it was never charged, nothing to refund.");
        }
        if ($order->status === 'pending') {
            return $this->deny($orderNumber, $request->all(), "Order {$orderNumber} is unpaid (status: pending) — nothing has been charged.");
        }

        $processedCents = $order->refunds->where('status', 'processed')->sum('amount_cents');
        $remainingCents = $order->total_cents - $processedCents;
        if ($remainingCents <= 0) {
            return $this->deny($orderNumber, $request->all(), "Order {$orderNumber} is already fully refunded — double refunds are refused.");
        }

        $pending = $order->refunds->where('status', 'pending')->first();
        if ($pending) {
            \App\Mcp\Support\McpAudit::record('request-refund', $request->all(), "reused pending request #{$pending->id} on {$orderNumber}");

            return Response::text(sprintf(
                'A pending refund request already exists for %s — request #%d for $%.2f. It awaits human approval (`php artisan refund:approve %d`).',
                $orderNumber, $pending->id, $pending->amount_cents / 100, $pending->id
            ));
        }

        $amountCents = $request->integer('amount_cents') ?: $remainingCents;
        if ($amountCents > $remainingCents) {
            return $this->deny($orderNumber, $request->all(), sprintf(
                'Only $%.2f is still refundable on %s — refused to draft $%.2f.',
                $remainingCents / 100, $orderNumber, $amountCents / 100
            ));
        }

        $refund = $order->refunds()->create([
            'amount_cents' => $amountCents,
            'reason' => $request->string('reason') ?: null,
            'idempotency_key' => 'req-'.bin2hex(random_bytes(8)),
            'status' => 'pending',
            'processed_at' => null,
        ]);

        \App\Mcp\Support\McpAudit::record('request-refund', $request->all(), "drafted request #{$refund->id} for \${$this->dollars($amountCents)} on {$orderNumber}");

        return Response::text(sprintf(
            'Refund request #%d drafted for %s: $%.2f. A human must approve it — `php artisan refund:approve %d` — before issue-refund will execute.',
            $refund->id, $orderNumber, $amountCents / 100, $refund->id
        ));
    }

    protected function dollars(int $cents): string
    {
        return number_format($cents / 100, 2);
    }

    // Refusals are audited too — a drafted-then-refused request leaves a row.
    protected function deny(string $orderNumber, array $arguments, string $message): Response
    {
        \App\Mcp\Support\McpAudit::record('request-refund', $arguments, 'refused: '.$message);

        return Response::error($message);
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
                ->description('Requested refund in cents. Omit to request the full remaining refundable balance.'),
            'reason' => $schema->string()
                ->description('Reason shown to the approving human, e.g. "damaged on arrival".'),
        ];
    }
}
