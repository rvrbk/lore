<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Database\Factories\VaultItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One saved login. The username, password and notes are encrypted with the application key before they reach the database.
 * They can be read back, so they are encrypted and never hashed.
 */
#[Fillable(['name', 'url', 'username', 'password', 'notes'])]
#[Hidden(['password'])]
class VaultItem extends Model
{
    /** @use HasFactory<VaultItemFactory> */
    use BelongsToUser, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'username' => 'encrypted',
            'password' => 'encrypted',
            'notes' => 'encrypted',
            'last_revealed_at' => 'datetime',
        ];
    }

    /**
     * Items whose name or URL contains the text. Only those two columns are searchable: the rest is ciphertext.
     *
     * @param  Builder<VaultItem>  $query
     */
    public function scopeMatching(Builder $query, ?string $search): void
    {
        $search = trim((string) $search);
        if ($search === '') {
            return;
        }

        $like = '%'.addcslashes($search, '\\%_').'%';
        $query->where(fn (Builder $q) => $q->whereRaw("lower(name) like ? escape '\\'", [mb_strtolower($like)])->orWhereRaw("lower(url) like ? escape '\\'", [mb_strtolower($like)]));
    }
}
