<?php

namespace App\Services\Vault;

use App\Models\VaultItem;

/**
 * Writes vault items in the CSV format Bitwarden imports (and most other password managers read).
 */
class BitwardenCsv
{
    /** @var list<string> */
    public const COLUMNS = ['folder', 'favorite', 'type', 'name', 'notes', 'fields', 'reprompt', 'login_uri', 'login_username', 'login_password', 'login_totp'];

    /**
     * @param  iterable<VaultItem>  $items
     * @param  resource  $out
     */
    public function write($out, iterable $items): void
    {
        $this->put($out, self::COLUMNS);
        foreach ($items as $item) {
            $this->put($out, ['', '', 'login', $item->name, (string) $item->notes, '', '0', (string) $item->url, (string) $item->username, $item->password, '']);
        }
    }

    /**
     * @param  resource  $out
     * @param  list<string>  $row
     */
    private function put($out, array $row): void
    {
        fputcsv($out, $row, ',', '"', '', "\r\n");
    }
}
