<?php

namespace App\Mcp\Tools;

use App\Models\Product;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

// Lookup key — tools/call addresses the tool by this string.
#[Name('check-inventory')]
// What the model reads to decide whether this tool answers its question.
#[Description('Check stock and price for products by SKU or name fragment. Returns live inventory counts.')]
class CheckInventoryTool extends Tool
{
    /**
     * The only code that executes on a call — eloquent in, text out.
     */
    public function handle(Request $request): Response
    {
        $products = Product::query()
            ->where('sku', 'like', "%{$request->get('query')}%")
            ->orWhere('name', 'like', "%{$request->get('query')}%")
            ->orderBy('sku')
            ->limit(10)
            ->get();

        if ($products->isEmpty()) {
            return Response::text('No products matched.');
        }

        $lines = $products->map(fn (Product $p) => sprintf(
            '%s · %s · $%.2f · %d in stock%s',
            $p->sku,
            $p->name,
            $p->price_cents / 100,
            $p->stock,
            $p->active ? '' : ' (discontinued)',
        ));

        return Response::text($lines->join("\n"));
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
                ->description('SKU or product-name fragment, e.g. "anvil" or "ANV-10KG".')
                ->required(),
        ];
    }
}
