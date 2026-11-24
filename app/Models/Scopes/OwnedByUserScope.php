<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Limits a model to the rows of the user whose request is being handled.
 *
 * The user is announced by the ScopeToCurrentUser middleware, which only wraps the signed-in part of the site. Public pages
 * (guest booking), the scheduler and console commands therefore see every user's rows, and must pick the owner themselves.
 */
class OwnedByUserScope implements Scope
{
    /** The container key under which the middleware announces the current user's id. */
    public const KEY = 'current-owner-id';

    public function apply(Builder $builder, Model $model): void
    {
        if (app()->bound(self::KEY)) {
            $builder->where($model->qualifyColumn('user_id'), app(self::KEY));
        }
    }
}
