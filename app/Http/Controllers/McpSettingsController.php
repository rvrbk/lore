<?php

namespace App\Http\Controllers;

use App\Support\McpClients;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Where a user makes and revokes the bearer tokens that let an MCP client (such as Claude Code) read and write their Lore.
 */
class McpSettingsController extends Controller
{
    public function show(Request $request): View
    {
        $endpoint = route('mcp.lore');

        return view('settings.mcp', [
            'endpoint' => $endpoint,
            'clients' => McpClients::snippets('lore', $endpoint, session('new_token') ?? '<your token>'),
            'tokens' => $request->user()->tokens()->latest('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $name = $request->validate(['name' => ['required', 'string', 'max:60']])['name'];

        return redirect()->route('settings.mcp')->with('new_token', $request->user()->createToken($name)->plainTextToken);
    }

    public function destroy(Request $request, int $token): RedirectResponse
    {
        $request->user()->tokens()->whereKey($token)->firstOrFail()->delete();

        return redirect()->route('settings.mcp')->with('status', 'Token revoked. Clients using it can no longer connect.');
    }
}
