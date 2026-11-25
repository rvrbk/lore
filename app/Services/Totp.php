<?php

namespace App\Services;

use PragmaRX\Google2FA\Google2FA;

/**
 * Time-based one-time passwords (RFC 6238: HMAC-SHA1, 6 digits, 30 second steps), as every authenticator app expects.
 * The arithmetic is done by pragmarx/google2fa; this class keeps the step bookkeeping used to refuse replayed codes.
 */
class Totp
{
    private const PERIOD = 30;

    private const DIGITS = 6;

    /** A fresh 160-bit secret, base32 encoded so it can be typed into an authenticator app. */
    public function generateSecret(): string
    {
        return (new Google2FA)->generateSecretKey(32);
    }

    /** The six digit code for the 30 second step the given time falls in. */
    public function code(#[\SensitiveParameter] string $secret, int $step): string
    {
        return (new Google2FA)->oathTotp($secret, $step);
    }

    public function currentStep(?int $timestamp = null): int
    {
        return intdiv($timestamp ?? time(), self::PERIOD);
    }

    /**
     * The step the code is valid for (one step of clock drift either way is tolerated), or null when it does not match.
     * Callers remember the returned step so the same code cannot be used twice.
     */
    public function matchingStep(#[\SensitiveParameter] string $secret, string $code, ?int $timestamp = null): ?int
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if (! preg_match('/^\d{'.self::DIGITS.'}$/', $code)) {
            return null;
        }

        $now = $this->currentStep($timestamp);
        foreach ([0, -1, 1] as $drift) {
            if (hash_equals($this->code($secret, $now + $drift), $code)) {
                return $now + $drift;
            }
        }

        return null;
    }

    /** The otpauth:// link authenticator apps understand. */
    public function uri(#[\SensitiveParameter] string $secret, string $account, string $issuer): string
    {
        return 'otpauth://totp/'.rawurlencode($issuer.':'.$account).'?'.http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => self::DIGITS,
            'period' => self::PERIOD,
        ], '', '&', PHP_QUERY_RFC3986);
    }
}
