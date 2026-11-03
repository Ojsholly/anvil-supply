<?php

namespace App\Console\Commands;

use App\Mcp\Support\McpAudit;
use App\Models\Refund;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('refund:approve {id : The refund request ID}')]
#[Description('Approve a pending refund request — the human signature the issue-refund tool requires')]
class RefundApproveCommand extends Command
{
    /**
     * Approving here is the ONLY way a refund becomes executable. The agent
     * cannot reach this path — approved_at can't be set through MCP.
     */
    public function handle()
    {
        $refund = Refund::with('order')->find($this->argument('id'));

        if (! $refund) {
            $this->error("No refund request #{$this->argument('id')}.");

            return self::FAILURE;
        }
        if ($refund->status !== 'pending') {
            $this->error("Request #{$refund->id} is '{$refund->status}', not pending — nothing to approve.");

            return self::FAILURE;
        }

        $refund->update([
            'status' => 'approved',
            'approved_by' => 'human-operator',
            'approved_at' => now(),
        ]);

        McpAudit::record('refund:approve', ['refund_id' => $refund->id], sprintf(
            'approved request #%d — $%.2f on %s',
            $refund->id, $refund->amount_cents / 100, $refund->order?->number
        ));

        $this->info("Approved #{$refund->id}: \${$this->dollars($refund->amount_cents)} on {$refund->order?->number}. issue-refund can now execute it.");

        return self::SUCCESS;
    }

    protected function dollars(int $cents): string
    {
        return number_format($cents / 100, 2);
    }
}
