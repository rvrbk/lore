<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Access tokens for the Google service account (the same key file Analytics uses), one per API scope, with no extra packages.
 */
class GoogleServiceAccount
{
    public function hasCredentials(): bool
    {
        return is_readable((string) config('services.google_analytics.credentials'));
    }

    public function token(string $scope): string
    {
        return Cache::remember('google-token.'.md5($scope), now()->addMinutes(50), function () use ($scope): string {
            $key = json_decode((string) file_get_contents(config('services.google_analytics.credentials')), true);
            $encode = fn (string $data): string => rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
            $now = time();
            $unsigned = $encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])).'.'.$encode(json_encode([
                'iss' => $key['client_email'], 'scope' => $scope, 'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $now, 'exp' => $now + 3600,
            ]));
            openssl_sign($unsigned, $signature, $key['private_key'], OPENSSL_ALGO_SHA256);

            return Http::asForm()->timeout(8)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $unsigned.'.'.$encode($signature),
            ])->throw()->json('access_token');
        });
    }
}
