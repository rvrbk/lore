<?php

use App\Http\Middleware\ScopeToCurrentUser;
use App\Mcp\Servers\LoreServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/lore', LoreServer::class)
    ->middleware(['auth:sanctum', ScopeToCurrentUser::class, 'throttle:120,1'])
    ->name('mcp.lore');
