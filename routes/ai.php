<?php

use App\Mcp\Servers\HelpDeskServer;
use Laravel\Mcp\Facades\Mcp;

/*
|--------------------------------------------------------------------------
| MCP Server Routes
|--------------------------------------------------------------------------
|
| OAuth 2.1 discovery routes are registered first so MCP clients can
| auto-discover the authorization server and token endpoint.
|
| The HelpDesk MCP server requires an authenticated Passport token
| (auth:api) — MCP clients go through the authorization code + PKCE
| flow before being granted access.
|
| Endpoint: POST /mcp/helpdesk
| Auth:     OAuth 2.1 via Laravel Passport (auth:api)
|
*/

Mcp::oauthRoutes();

Mcp::web('/mcp/helpdesk', HelpDeskServer::class)
    ->middleware('auth:api');
