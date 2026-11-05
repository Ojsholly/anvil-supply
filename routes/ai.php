<?php

use App\Mcp\Servers\StoreServer;
use Laravel\Mcp\Facades\Mcp;

// OAuth 2.1 plumbing for the HTTP transport — registers the
// .well-known discovery endpoints MCP clients probe first, plus the
// dynamic-client-registration endpoint (/oauth/register).
Mcp::oauthRoutes();

// Remote (HTTP) server — the same registry, but reached over POST instead of
// stdio. auth:api means every request must carry a Passport access token for
// the mcp:use scope — the agent has no shell here; identity arrives with the
// request. This is the "capability, not convention" wall from Part 2 made
// structural.
Mcp::web('/mcp/store', StoreServer::class)->middleware('auth:api');

// Local (stdio) server — spawned per-agent as `php artisan mcp:start anvil-supply`.
Mcp::local('anvil-supply', StoreServer::class);
