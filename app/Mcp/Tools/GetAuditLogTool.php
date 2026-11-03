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
#[Name('get-audit-log')]
// What the model reads to decide whether this tool answers its question.
// This is the whole point of the tool: the ledger becomes a capability the
// agent can call, instead of a table it has to go around the server to read.
#[Description('Read the mcp_audit_log ledger — one row per tool call or human approval action: which actor, which tool, what arguments, and the result. Newest first. Use for "what did the agent do", approval history, or verifying a refund was actually executed.')]
#[IsReadOnly]
class GetAuditLogTool extends Tool
{
    /**
     * The only code that executes on a call — filtered query, text out.
     * Reading the ledger is itself a tool call, so it lands on the ledger.
     */
    public function handle(Request $request): Response
    {
        $limit = max(1, min(100, (int) ($request->get('limit') ?? 20)));

        $query = DB::table('mcp_audit_log')->orderByDesc('id');

        if ($actor = $request->get('actor')) {
            $query->where('actor', $actor);
        }
        if ($tool = $request->get('tool')) {
            $query->where('tool', $tool);
        }
        if ($orderNumber = $request->get('order_number')) {
            // JSON-extract the order_number out of each call's arguments —
            // matches rows like {"order_number":"AS-1052"}.
            $query->where('arguments->order_number', $orderNumber);
        }

        $rows = $query->limit($limit)->get();

        McpAudit::record('get-audit-log', $request->all(), $rows->count().' audit row(s) returned');

        if ($rows->isEmpty()) {
            return Response::text('No audit rows match those filters.');
        }

        return Response::text($rows->map(fn ($r) => sprintf(
            '#%d %s · %s · %s · %s — %s',
            $r->id,
            $r->created_at,
            $r->actor ?? 'unknown',
            $r->tool,
            $r->arguments ?? '{}',
            $r->result_summary
        ))->join("\n"));
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
            'actor' => $schema->string()
                ->description('Filter by actor — e.g. "local-agent" or "human-operator".'),
            'tool' => $schema->string()
                ->description('Filter by tool name — e.g. "issue-refund" or "refund:approve".'),
            'order_number' => $schema->string()
                ->description('Filter to calls whose arguments carry this order number — e.g. "AS-1052".'),
            'limit' => $schema->integer()
                ->description('Max rows to return, newest first (default 20, max 100).'),
        ];
    }
}
