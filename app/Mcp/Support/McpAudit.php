<?php

namespace App\Mcp\Support;

use Illuminate\Support\Facades\DB;

/**
 * Writes one row to mcp_audit_log per tool call. laravel/mcp v1.1.x has no
 * call-level middleware hook, so tools record themselves — the ledger of
 * what the agent asked for and what the server did about it.
 */
class McpAudit
{
    public static function record(string $tool, array $arguments, string $resultSummary, string $actor = 'local-agent'): void
    {
        DB::table('mcp_audit_log')->insert([
            'tool' => $tool,
            'arguments' => json_encode($arguments),
            'actor' => $actor,
            'result_summary' => $resultSummary,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
