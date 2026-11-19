<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Reads the number of new vacancy matches from Job Scout. Cached briefly; any failure just hides the number.
 */
class JobScout
{
    public function isConfigured(): bool
    {
        return config('services.job_scout.url') && config('services.job_scout.token') && config('services.job_scout.email');
    }

    /**
     * @return array{new: int, url: string}|null
     */
    public function newMatches(): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $result = Cache::remember('job-scout.new-matches', now()->addMinutes(5), function (): array|false {
            try {
                $response = Http::withToken(config('services.job_scout.token'))
                    ->acceptJson()->timeout(3)
                    ->get(rtrim(config('services.job_scout.url'), '/').'/integrations/lore/new-matches', ['email' => config('services.job_scout.email')]);
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
