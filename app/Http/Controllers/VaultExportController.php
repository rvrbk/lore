<?php

namespace App\Http\Controllers;

use App\Services\Vault\BitwardenCsv;
use App\Services\Vault\VaultSealer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VaultExportController extends Controller
{
    public function __invoke(Request $request, BitwardenCsv $csv, VaultSealer $sealer): Response
    {
        $format = $request->query('format', 'csv');
        abort_unless(in_array($format, ['csv', 'encrypted'], true), 422);

        if ($format === 'encrypted' && ! $sealer->isAvailable()) {
            return redirect()->route('vault.index')->with('error', 'Encrypted export is not set up: add VAULT_PUBLIC_KEY to the server .env (make the pair with scripts/vault-keygen.php).');
        }

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
