<?php

declare(strict_types=1);

namespace Tests\Support\Reporting;

/**
 * Replaces known secret values, and anything matching a sensitive-looking
 * pattern, with a fixed mask.
 */
final class Redactor
{
    public const MASK = '***REDACTED***';

    /**
     * Shorter values are ignored. An unset env var would otherwise register
     * the empty string and mangle every artifact, and a three-character value
     * would match half the English language.
     */
    public const MIN_SECRET_LENGTH = 8;

    private array $literals;

    private array $patterns;

    private function __construct(array $literals, array $patterns)
    {
        // Longest first, so a secret containing a shorter one is masked whole.
        uksort($literals, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));

        $this->literals = $literals;
        $this->patterns = $patterns;
    }

    /**
     * A redactor that knows no specific secrets but still catches
     * credential-shaped text.
     */
    public static function withDefaultPatterns(): self
    {
        return new self([], [
            // Authorization: Basic <b64> / Bearer <token>, in headers, JSON and logs.
            '/(Authorization["\']?\s*[:=]\s*["\']?)(Basic|Bearer)\s+[A-Za-z0-9+\/=._-]+/i'
                => '$1$2 ' . self::MASK,
            // Sensitive JSON/array keys whose values we cannot know in advance.
            '/(["\'](?:shared_secret|client_token|access_token|authtoken|api_key|password)["\']\s*[:=>]+\s*["\'])[^"\']*(["\'])/i'
                => '$1' . self::MASK . '$2',
            // Shell/env style assignments.
            '/\b(NGROK_AUTHTOKEN|KUSTOM_TEST_SECRET_[A-Z]{2}|WORDPRESS_ADMIN_PASSWORD)\s*=\s*\S+/'
                => '$1=' . self::MASK,
        ]);
    }

    /** Registers a secret and every encoding of it this codebase can emit. */
    public function withSecret(string $secret, string $label): self
    {
        $literals = $this->literals;

        foreach ($this->encodingsOf($secret) as $variant) {
            $literals[$variant] = $label;
        }

        return new self($literals, $this->patterns);
    }

    /**
     * Registers the base64 of `username:password`, which is the form the plugin
     * actually sends. Scrubbing the password alone would miss it.
     */
    public function withBasicAuthPair(string $username, string $password, string $label): self
    {
        if (strlen($username) === 0 || strlen($password) < self::MIN_SECRET_LENGTH) {
            return $this;
        }

        $literals = $this->literals;

        // The plugin decodes HTML entities out of the secret before encoding, so cover both spellings.
        foreach ([$password, htmlspecialchars_decode($password, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401)] as $candidate) {
            $literals[base64_encode($username . ':' . $candidate)] = $label;
        }

        return (new self($literals, $this->patterns))->withSecret($password, $label);
    }

    public function scrub(string $text): string
    {
        foreach (array_keys($this->literals) as $literal) {
            $text = str_replace($literal, self::MASK, $text);
        }

        foreach ($this->patterns as $pattern => $replacement) {
            $text = (string) preg_replace($pattern, $replacement, $text);
        }

        return $text;
    }

    /** Every literal this redactor knows, mapped to the env var it came from. */
    public function literals(): array
    {
        return $this->literals;
    }

    private function encodingsOf(string $secret): array
    {
        if (strlen($secret) < self::MIN_SECRET_LENGTH) {
            return [];
        }

        $decoded = htmlspecialchars_decode($secret, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401);

        $variants = [
            $secret,
            $decoded,
            base64_encode($secret),
            base64_encode($decoded),
            rawurlencode($secret),
            urlencode($secret),
            // JSON-escaped, e.g. a secret containing a slash inside a body dump.
            trim((string) json_encode($secret), '"'),
        ];

        return array_values(array_unique(array_filter(
            $variants,
            static fn(string $v): bool => strlen($v) >= self::MIN_SECRET_LENGTH
        )));
    }
}
