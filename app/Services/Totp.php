<?php

namespace App\Services;

/**
 * Time-based one-time passwords (RFC 6238: HMAC-SHA1, 6 digits, 30 second steps), as every authenticator app expects.
 */
class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    private const PERIOD = 30;

    private const DIGITS = 6;

    /** A fresh 160-bit secret, base32 encoded so it can be typed into an authenticator app. */
    public function generateSecret(): string
    {
        return $this->base32Encode(random_bytes(20));
    }

    /** The six digit code for the 30 second step the given time falls in. */
    public function code(#[\SensitiveParameter] string $secret, int $step): string
    {
        $hash = hash_hmac('sha1', pack('J', $step), $this->base32Decode($secret), true);
        $offset = ord($hash[19]) & 0xF;
        $binary = ((ord($hash[$offset]) & 0x7F) << 24) | (ord($hash[$offset + 1]) << 16) | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]);

        return str_pad((string) ($binary % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
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

    private function base32Encode(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        return implode('', array_map(fn (string $chunk) => self::ALPHABET[bindec(str_pad($chunk, 5, '0'))], str_split($bits, 5)));
    }

    private function base32Decode(string $encoded): string
    {
        $bits = '';
        foreach (str_split(strtoupper(rtrim($encoded, '='))) as $char) {
            $bits .= str_pad(decbin((int) strpos(self::ALPHABET, $char)), 5, '0', STR_PAD_LEFT);
        }

        return implode('', array_map(fn (string $byte) => chr(bindec($byte)), str_split(substr($bits, 0, intdiv(strlen($bits), 8) * 8), 8)));
    }
}
