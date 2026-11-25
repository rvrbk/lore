<?php

namespace App\Http\Controllers;

use App\Services\Vault\VaultSealer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The public key the signed-in user's encrypted exports are locked to. Only the public half is ever stored here.
 */
class VaultKeySettingsController extends Controller
{
    public function show(Request $request): View
    {
        $key = $request->user()->vault_public_key;

        return view('settings.vault-key', ['hasKey' => $key !== null, 'fingerprint' => $key === null ? null : VaultSealer::fingerprint($key)]);
    }

    public function update(Request $request): RedirectResponse
    {
        $key = trim((string) $request->validate(['vault_public_key' => ['required', 'string', 'max:200']])['vault_public_key']);

        if (str_starts_with($key, VaultSealer::PRIVATE_PREFIX)) {
            throw ValidationException::withMessages(['vault_public_key' => 'That is your PRIVATE key. Never paste it here: keep it on your own computer and paste the public key instead.']);
        }
        if (! VaultSealer::isValidPublicKey($key)) {
            throw ValidationException::withMessages(['vault_public_key' => 'That is not a valid public key. It should start with lorepub1: and come from the key tool.']);
        }

        $request->user()->forceFill(['vault_public_key' => $key])->save();

        return redirect()->route('settings.vault-key')->with('status', 'Saved. Your encrypted exports are now locked to this key.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->user()->forceFill(['vault_public_key' => null])->save();

        return redirect()->route('settings.vault-key')->with('status', 'Removed. Encrypted exports are off until you add a key again.');
    }
}
