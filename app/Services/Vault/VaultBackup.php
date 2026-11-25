<?php

namespace App\Services\Vault;

use App\Models\User;
use App\Models\VaultItem;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Sealed backups of the whole vault.
 *
 * The backup holds the decrypted items, sealed to the public key, so it does not depend on this install's APP_KEY and
 * can be restored on any install. Without the private key it is unreadable.
 */
class VaultBackup
{
    public function __construct(private readonly VaultSealer $sealer) {}

    /** Writes a new sealed backup and applies the retention rules. Returns its path. */
    public function create(): string
    {
        $dir = config('vault.backup.path');
        File::ensureDirectoryExists($dir, 0700);

        $path = $dir.'/vault-'.now()->format('Ymd-His').'.sealed';
        $temporary = $path.'.tmp';
        File::put($temporary, $this->sealer->seal($this->snapshot()));
        chmod($temporary, 0600);
        rename($temporary, $path);

        (new VaultBackupRetention)->prune($dir, (int) config('vault.backup.keep_daily'), (int) config('vault.backup.keep_weekly'));

        return $path;
    }

    private function snapshot(): string
    {
        $items = [];
        foreach (VaultItem::withoutGlobalScopes()->orderBy('id')->lazy() as $item) {
            $items[] = [
                'id' => $item->id,
                'user_id' => $item->user_id,
                'name' => $item->name,
                'url' => $item->url,
                'username' => $item->username,
                'password' => $item->password,
                'notes' => $item->notes,
                'last_revealed_at' => $item->last_revealed_at?->toIso8601String(),
                'created_at' => $item->created_at?->toIso8601String(),
                'updated_at' => $item->updated_at?->toIso8601String(),
            ];
        }

        return json_encode([
            'version' => 1,
            'created_at' => now()->toIso8601String(),
            'users' => User::whereIn('id', array_unique(array_column($items, 'user_id')))->pluck('username', 'id')->all(),
            'items' => $items,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Puts the items of a decrypted backup back, creating or overwriting by id, and re-encrypting them with this install's key.
     *
     * @return array{restored: int, skipped: int}
     */
    public function restore(#[\SensitiveParameter] string $json, bool $dryRun = false): array
    {
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        if (($data['version'] ?? null) !== 1 || ! is_array($data['items'] ?? null)) {
            throw new RuntimeException('Unknown backup format.');
        }

        $restored = $skipped = 0;
        foreach ($data['items'] as $row) {
            $userId = $this->resolveUser((int) $row['user_id'], $data['users'][$row['user_id']] ?? null);
            if ($userId === null) {
                $skipped++;

                continue;
            }

            if (! $dryRun) {
                $item = VaultItem::withoutGlobalScopes()->findOrNew($row['id']);
                $item->timestamps = false;
                $item->forceFill([
                    'id' => $row['id'],
                    'user_id' => $userId,
                    'name' => $row['name'],
                    'url' => $row['url'],
                    'username' => $row['username'],
                    'password' => $row['password'],
                    'notes' => $row['notes'],
                    'last_revealed_at' => $row['last_revealed_at'],
                    'created_at' => $row['created_at'],
                    'updated_at' => $row['updated_at'],
                ])->save();
            }
            $restored++;
        }

        return ['restored' => $restored, 'skipped' => $skipped];
    }

    /** The same id with the same username, or else whoever has that username here. */
    private function resolveUser(int $id, ?string $username): ?int
    {
        $byId = User::find($id);
        if ($byId !== null && ($username === null || $byId->username === $username)) {
            return $byId->id;
        }

        return $username === null ? null : User::where('username', $username)->value('id');
    }
}
