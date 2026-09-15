<?php

declare(strict_types=1);

namespace Tests\Harness\Reporting;

use lucatume\WPBrowser\TestCase\WPTestCase;
use Tests\Support\Reporting\Redactor;

/**
 * The Redactor is the only thing standing between a real Kustom test secret
 * and a downloadable CI artifact, so every variant the plugin can emit is pinned
 * here. WPTestCase, not Unit: Unit's global-state snapshot serialises the live
 * WooCommerce instance, which fatals on __wakeup.
 */
class RedactorTest extends WPTestCase
{
    public function testScrubsARawSecret(): void
    {
        $redactor = Redactor::withDefaultPatterns()->withSecret('sup3r-s3cret-value', 'KUSTOM_TEST_SECRET_EU');

        $this->assertSame(
            'token=' . Redactor::MASK,
            $redactor->scrub('token=sup3r-s3cret-value')
        );
    }

    public function testScrubsTheBase64FormOfASecret(): void
    {
        $redactor = Redactor::withDefaultPatterns()->withSecret('sup3r-s3cret-value', 'KUSTOM_TEST_SECRET_EU');
        $encoded  = base64_encode('sup3r-s3cret-value');

        $this->assertStringNotContainsString($encoded, $redactor->scrub("blob {$encoded} blob"));
    }

    public function testScrubsTheUrlEncodedFormOfASecret(): void
    {
        $redactor = Redactor::withDefaultPatterns()->withSecret('a secret/value', 'KUSTOM_TEST_SECRET_EU');
        $encoded  = rawurlencode('a secret/value');

        $this->assertStringNotContainsString($encoded, $redactor->scrub("?k={$encoded}"));
    }

    public function testScrubsTheBasicAuthHeaderPair(): void
    {
        // Exactly what KCO_Request::get_request_headers() builds.
        $header = 'Basic ' . base64_encode('K123456_abc:sup3r-s3cret-value');

        $redactor = Redactor::withDefaultPatterns()
            ->withBasicAuthPair('K123456_abc', 'sup3r-s3cret-value', 'KUSTOM_TEST_SECRET_EU');

        $this->assertStringNotContainsString(
            base64_encode('K123456_abc:sup3r-s3cret-value'),
            $redactor->scrub("Authorization: {$header}")
        );
    }

    public function testScrubsAnUnknownBearerTokenByPattern(): void
    {
        $redactor = Redactor::withDefaultPatterns();

        $this->assertStringNotContainsString(
            'eyJhbGciOiJIUzI1NiJ9',
            $redactor->scrub('Authorization: Bearer eyJhbGciOiJIUzI1NiJ9')
        );
    }

    public function testScrubsSensitiveJsonKeysWhoseValuesAreUnknown(): void
    {
        $redactor = Redactor::withDefaultPatterns();
        $scrubbed = $redactor->scrub('{"client_token":"nope-not-this","order_amount":25000}');

        $this->assertStringNotContainsString('nope-not-this', $scrubbed);
        $this->assertStringContainsString('25000', $scrubbed, 'Non-sensitive fields must survive.');
    }

    public function testIgnoresEmptyAndShortSecrets(): void
    {
        // An unset KUSTOM_TEST_SECRET_EU must not turn into a str_replace('')
        $redactor = Redactor::withDefaultPatterns()
            ->withSecret('', 'EMPTY')
            ->withSecret('yes', 'TOO_SHORT');

        $this->assertSame('yes it is', $redactor->scrub('yes it is'));
        $this->assertSame([], $redactor->literals());
    }

    public function testPrefersTheLongestMatchWhenSecretsOverlap(): void
    {
        $redactor = Redactor::withDefaultPatterns()
            ->withSecret('abcdefgh', 'SHORT')
            ->withSecret('abcdefghijkl', 'LONG');

        $this->assertSame(Redactor::MASK, $redactor->scrub('abcdefghijkl'));
    }
}
