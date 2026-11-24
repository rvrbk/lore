<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Reads a user's number of new vacancy matches from Job Scout, using the email of the Job Scout account on their profile.
 * Cached briefly; any failure, or a user without a Job Scout account, just hides the number.
 */
class JobScout
{
    public function isConfigured(?User $user = null): bool
    {
        $user ??= Auth::user();

        return config('services.job_scout.url') && config('services.job_scout.token') && filled($user?->job_scout_email);
    }

    /**
     * @return array{new: int, url: string}|null
     */
    public function newMatches(?User $user = null): ?array
    {
        $user ??= Auth::user();
        if (! $this->isConfigured($user)) {
            return null;
        }

        $result = Cache::remember('job-scout.new-matches.'.$user->id, now()->addMinutes(5), function () use ($user): array|false {
            try {
                $response = Http::withToken(config('services.job_scout.token'))
                    ->acceptJson()->timeout(3)
                    ->get(rtrim(config('services.job_scout.url'), '/').'/integrations/lore/new-matches', ['email' => $user->job_scout_email]);
            } catch (Throwable) {
                return false;
            }

            return $response->successful() && is_int($response->json('new'))
                ? ['new' => $response->json('new'), 'url' => (string) $response->json('url')]
                : false;
        });

        return $result ?: null;
    }
}
