<?php

namespace App\Console\Commands;

use App\Models\Refund;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('refund:requests')]
#[Description('List refund requests awaiting human review')]
class RefundRequestsCommand extends Command
{
    /**
     * The human's side of the MCP boundary: pending requests drafted by the
     * agent show up here; approve or reject them from this terminal.
     */
    public function handle()
    {
        $requests = Refund::with('order')
            ->whereIn('status', ['pending', 'approved'])
            ->latest()
            ->get();

        if ($requests->isEmpty()) {
            $this->info('No refund requests awaiting review.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Order', 'Amount', 'Reason', 'Status', 'Requested'],
            $requests->map(fn (Refund $r) => [
                $r->id,
                $r->order?->number ?? '?',
                sprintf('$%.2f', $r->amount_cents / 100),
                $r->reason ?? '—',
                $r->status,
                $r->created_at->diffForHumans(),
            ])
        );

        $this->line('Approve: php artisan refund:approve {id}   Reject: php artisan refund:reject {id}');

        return self::SUCCESS;
    }
}
