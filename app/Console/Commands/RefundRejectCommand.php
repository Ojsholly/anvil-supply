<?php

namespace App\Console\Commands;

use App\Mcp\Support\McpAudit;
use App\Models\Refund;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('refund:reject {id : The refund request ID} {reason? : Why it was rejected}')]
#[Description('Reject a pending refund request')]
class RefundRejectCommand extends Command
{
    public function handle()
    {
        $refund = Refund::with('order')->find($this->argument('id'));

        if (! $refund) {
            $this->error("No refund request #{$this->argument('id')}.");

            return self::FAILURE;
        }
        if ($refund->status !== 'pending') {
            $this->error("Request #{$refund->id} is '{$refund->status}', not pending — nothing to reject.");

            return self::FAILURE;
        }

        $reason = $this->argument('reason');
        $refund->update([
            'status' => 'rejected',
            'reason' => $reason ? $refund->reason." [rejected: {$reason}]" : $refund->reason,
        ]);

        McpAudit::record('refund:reject', ['refund_id' => $refund->id, 'reason' => $reason], sprintf(
            'rejected request #%d — $%.2f on %s',
            $refund->id, $refund->amount_cents / 100, $refund->order?->number
        ));

        $this->info("Rejected #{$refund->id} on {$refund->order?->number}.");

        return self::SUCCESS;
    }
}
