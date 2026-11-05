<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\CheckInventoryTool;
use App\Mcp\Tools\GetAuditLogTool;
use App\Mcp\Tools\GetCustomerTool;
use App\Mcp\Tools\GetOrderTool;
use App\Mcp\Tools\GetTopCustomersTool;
use App\Mcp\Tools\IssueRefundTool;
use App\Mcp\Tools\RequestRefundTool;
use App\Mcp\Tools\SearchOrdersTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

// Server identity — reported to the client in the initialize handshake.
#[Name('Anvil Supply — Store')]
#[Version('1.0.0')]

// Standing guidance injected for every agent that connects — treat it like a
// system prompt for how these tools should be used.
#[Instructions(<<<'TXT'
    Anvil Supply Co. store backend. Use these tools to answer customer-support
    questions about orders, customers, and inventory.

    - Names are NOT unique in this database. Before reporting on a customer,
      confirm which person you mean by company or email.
    - Order numbers look like "AS-1044".
    - Money is returned in cents; quote dollars to the customer.
    - The audit ledger is readable via get-audit-log — prefer it over
      guessing what earlier calls did.
    TXT)]
class StoreServer extends Server
{
    /**
     * The registry — tools/list can only ever enumerate what is in this array.
     * Not listed, not callable.
     *
     * @var array<int, class-string>
     */
    protected array $tools = [
        SearchOrdersTool::class,
        GetOrderTool::class,
        GetCustomerTool::class,
        CheckInventoryTool::class,
        GetTopCustomersTool::class,
        RequestRefundTool::class,
        IssueRefundTool::class,
        GetAuditLogTool::class,
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];
}
