<?php

namespace App\Http\Controllers;

use App\Services\Vault\BitwardenCsv;
use App\Services\Vault\VaultSealer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class VaultExportController extends Controller
{
    /** Successful downloads allowed per hour. Wrong passwords do not use these up; they have their own, separate limit. */
    private const DOWNLOADS_PER_HOUR = 3;

    /** The page with the warnings. Nothing is downloaded until the form on it is submitted with the password. */
    public function show(VaultSealer $sealer): View
    {
        return view('vault.export', ['canExportEncrypted' => $sealer->isAvailable()]);
    }

    /** Every download asks for the account password again, however recently it was confirmed. */
    public function download(Request $request, BitwardenCsv $csv, VaultSealer $sealer): Response|RedirectResponse
    {
        $data = $request->validate([
            'format' => ['required', 'in:csv,encrypted'],
            'password' => ['required', 'string'],
            'acknowledge' => ['accepted'],
        ], ['acknowledge.accepted' => 'Tick the box to confirm you understand the risks.']);

        if (! Auth::guard('web')->validate(['username' => $request->user()->username, 'password' => $data['password']])) {
            throw ValidationException::withMessages(['password' => 'That is not your password.']);
        }

        $limiterKey = 'vault-export:'.$request->user()->id;
        abort_if(RateLimiter::tooManyAttempts($limiterKey, self::DOWNLOADS_PER_HOUR), 429, 'Too many exports. Try again later.');

        $format = $data['format'];
        if ($format === 'encrypted' && ! $sealer->isAvailable()) {
            return redirect()->route('vault.export')->with('error', 'Encrypted export is not set up: add VAULT_PUBLIC_KEY to the server .env (make the pair with scripts/vault-keygen.php).');
        }

        RateLimiter::hit($limiterKey, 3600);

        $items = $request->user()->vaultItems()->orderBy('id');
        $stamp = now()->format('Y-m-d');

        Log::channel(config('vault.log_channel'))->info('vault exported', [
            'user_id' => $request->user()->id,
            'ip' => $request->ip(),
            'format' => $format,
            'at' => now()->toIso8601String(),
        ]);

        if ($format === 'encrypted') {
            $buffer = fopen('php://temp', 'w+');
            $csv->write($buffer, $items->lazy());
            rewind($buffer);
            $sealed = $sealer->seal(stream_get_contents($buffer));
            fclose($buffer);

            return response($sealed, 200, [
                'Content-Type' => 'application/octet-stream',
                'Content-Disposition' => 'attachment; filename="lore-vault-'.$stamp.'.csv.sealed"',
            ]);
        }

        return response()->streamDownload(function () use ($csv, $items): void {
            $out = fopen('php://output', 'w');
            $csv->write($out, $items->lazy());
            fclose($out);
        }, 'lore-vault-'.$stamp.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
