<?php

use App\Mcp\Servers\StoreServer;
use Laravel\Mcp\Facades\Mcp;

// Mcp::web('/mcp/demo', \App\Mcp\Servers\PublicServer::class);

// Local (stdio) server — spawned per-agent as `php artisan mcp:start anvil-supply`.
Mcp::local('anvil-supply', StoreServer::class);
