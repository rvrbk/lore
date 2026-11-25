<?php

namespace Tests\Unit;

use App\Services\Totp;
use PHPUnit\Framework\TestCase;

class TotpTest extends TestCase
{
    /** RFC 6238 appendix B: the SHA-1 secret "12345678901234567890" at Unix time 59 gives 94287082, so 287082 with six digits. */
    private const RFC_SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    public function test_it_matches_the_rfc_6238_test_vectors(): void
    {
        $totp = new Totp;

        $this->assertSame('287082', $totp->code(self::RFC_SECRET, $totp->currentStep(59)));
        $this->assertSame('081804', $totp->code(self::RFC_SECRET, $totp->currentStep(1111111109)));
        $this->assertSame('050471', $totp->code(self::RFC_SECRET, $totp->currentStep(1111111111)));
    }

    public function test_it_accepts_one_step_of_drift_but_not_more_and_reports_the_step(): void
    {
        $totp = new Totp;

        $this->assertSame(1, $totp->matchingStep(self::RFC_SECRET, '287082', 59));
        $this->assertSame(1, $totp->matchingStep(self::RFC_SECRET, '287 082', 59 + 30));
        $this->assertNull($totp->matchingStep(self::RFC_SECRET, '287082', 59 + 90));
        $this->assertNull($totp->matchingStep(self::RFC_SECRET, 'abcdef', 59));
    }

    public function test_generated_secrets_are_base32_and_unique(): void
    {
        $totp = new Totp;
        $secret = $totp->generateSecret();

        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $secret);
        $this->assertNotSame($secret, $totp->generateSecret());
        $this->assertStringStartsWith('otpauth://totp/Lore%3Arik?secret='.$secret, $totp->uri($secret, 'rik', 'Lore'));
    }
}
