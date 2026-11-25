<?php

namespace App\Http\Controllers;

use App\Http\Requests\VaultItemRequest;
use App\Models\VaultItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class VaultController extends Controller
{
    public function index(Request $request): View
    {
        return view('vault.index', [
            'items' => $request->user()->vaultItems()->matching($request->query('q'))->orderBy('name')->get(['id', 'name', 'url', 'username', 'last_revealed_at']),
            'search' => (string) $request->query('q'),
            'idleLockMinutes' => config('vault.idle_lock_minutes'),
        ]);
    }

    /**
     * The saved logins as JSON for the browser extension: no passwords, same data as the list page. The CSRF token lets the
     * extension make the reveal POST with the session the person already unlocked in the browser.
     */
    public function items(Request $request): JsonResponse
    {
        return response()->json([
            'csrf' => csrf_token(),
            'items' => $request->user()->vaultItems()->matching($request->query('q'))->orderBy('name')->get(['id', 'name', 'url', 'username'])->map(fn (VaultItem $item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'url' => $item->url,
                'username' => $item->username,
                'reveal' => route('vault.reveal', $item),
            ])->all(),
        ]);
    }

    public function store(VaultItemRequest $request): RedirectResponse
    {
        $request->user()->vaultItems()->create($request->itemData());

        return redirect()->route('vault.index')->with('status', 'Saved.');
    }

    public function edit(VaultItem $item): View
    {
        Gate::authorize('update', $item);

        return view('vault.edit', ['item' => $item->makeHidden('password')]);
    }

    public function update(VaultItemRequest $request, VaultItem $item): RedirectResponse
    {
        $item->update($request->itemData());

        return redirect()->route('vault.index')->with('status', 'Saved.');
    }

    public function destroy(VaultItem $item): RedirectResponse
    {
        Gate::authorize('delete', $item);
        $item->delete();

        return redirect()->route('vault.index')->with('status', 'Deleted.');
    }

    /** The only way a password leaves the server: one item, on request, with an audit line that never contains the value. */
    public function reveal(Request $request, VaultItem $item): JsonResponse
    {
        Gate::authorize('reveal', $item);

        $item->timestamps = false;
        $item->forceFill(['last_revealed_at' => now()])->saveQuietly();
        Log::channel(config('vault.log_channel'))->info('vault item revealed', [
            'user_id' => $request->user()->id,
            'item_id' => $item->id,
            'ip' => $request->ip(),
        ]);

        return response()->json(['password' => $item->makeVisible('password')->password]);
    }
}
