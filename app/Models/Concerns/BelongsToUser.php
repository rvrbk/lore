<?php

namespace App\Models\Concerns;

use App\Models\Scopes\OwnedByUserScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Marks a model as owned by a user: signed-in requests only see their own rows, and new rows are given to the signed-in user, unless the code creating them names the owner.
 *
 * @mixin Model
 */
trait BelongsToUser
{
    protected static function bootBelongsToUser(): void
    {
        static::addGlobalScope(new OwnedByUserScope);

        static::creating(function (Model $model): void {
            if ($model->getAttribute('user_id') === null) {
                $model->setAttribute('user_id', Auth::id());
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
