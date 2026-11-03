<?php

namespace App\Mcp\Tools;

use App\Models\Customer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

// Lookup key — tools/call addresses the tool by this string.
#[Name('get-customer')]
// What the model reads to decide whether this tool answers its question.
#[Description('Look up customers by name or email. Names are NOT unique — this tool may return several people; always confirm which one is meant (company or email) before reporting their orders.')]
#[IsReadOnly]
class GetCustomerTool extends Tool
{
    /**
     * The only code that executes on a call — eloquent in, text out.
     */
    public function handle(Request $request): Response
    {
        $customers = Customer::query()
            ->withCount('orders')
            ->where('name', 'like', "%{$request->get('query')}%")
            ->orWhere('email', 'like', "%{$request->get('query')}%")
            ->limit(10)
            ->get();

        if ($customers->isEmpty()) {
            \App\Mcp\Support\McpAudit::record('get-customer', $request->all(), '0 customers matched');

            return Response::text('No customers matched.');
        }

        $lines = $customers->map(fn (Customer $c) => sprintf(
            '#%d %s <%s>%s · %d order(s)%s',
            $c->id,
            $c->name,
            $c->email,
            $c->company ? " — {$c->company}" : '',
            $c->orders_count,
            $c->notes ? " · note: {$c->notes}" : '',
        ));

        $prefix = $customers->count() > 1
            ? $customers->count()." matches — disambiguate before answering:\n"
            : '';

        \App\Mcp\Support\McpAudit::record('get-customer', $request->all(), $customers->count().' customer(s) matched');

        return Response::text($prefix.$lines->join("\n"));
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
            'query' => $schema->string()
                ->description('Name or email fragment to match.')
                ->required(),
        ];
    }
}
