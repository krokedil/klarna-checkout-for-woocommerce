<?php

declare(strict_types=1);

namespace Tests\Support\Reporting;

/**
 * The single place that knows which environment values are secret. Reads tests/.env and the
 * real environment, and hands back a primed Redactor. Real env vars win, so CI can inject
 * credentials without a .env file. Regional KUSTOM_TEST_SECRET_<CC> keys are discovered
 * dynamically, so a new region needs no code change.
 */
final class SecretRegistry
{
    /**
     * Env vars whose values must never appear in an artifact.
     *
     * @var array<int, string>
     */
    public const SECRET_KEYS = [
        'KUSTOM_TEST_SECRET_EU',
        'NGROK_AUTHTOKEN',
        'WORDPRESS_ADMIN_PASSWORD',
    ];

    /**
     * Merchant-id / secret pairs, as `[midKey, secretKey]`. The plugin sends these
     * base64'd together, so the pair needs registering as well as the parts.
     *
     * @var array<int, array{0: string, 1: string}>
     */
    private const BASIC_AUTH_PAIRS = [
        ['KUSTOM_TEST_MID_EU', 'KUSTOM_TEST_SECRET_EU'],
    ];

    /** Builds a Redactor from the environment. */
    public static function fromEnvironment(?string $envFile = null, ?array $env = null): Redactor
    {
        $envFile ??= dirname(__DIR__, 2) . '/.env';
        $values    = self::parseEnvFile($envFile);

        $read = static function (string $key) use ($values, $env): string {
            // Real environment wins, so CI can inject credentials without a file.
            if ($env === null) {
                $fromEnv = getenv($key);
                $fromEnv = is_string($fromEnv) ? $fromEnv : '';
            } else {
                $fromEnv = $env[$key] ?? '';
            }

            return $fromEnv !== '' ? $fromEnv : ($values[$key] ?? '');
        };

        $redactor = Redactor::withDefaultPatterns();

        // Any regional secret present in the file, plus the fixed list.
        $keys = self::SECRET_KEYS;
        foreach (array_keys($values) as $key) {
            if (preg_match('/^KUSTOM_TEST_SECRET_[A-Z]{2}$/', $key) === 1) {
                $keys[] = $key;
            }
        }

        foreach (array_unique($keys) as $key) {
            $value = $read($key);
            if ($value !== '') {
                $redactor = $redactor->withSecret($value, $key);
            }
        }

        foreach (self::BASIC_AUTH_PAIRS as [$midKey, $secretKey]) {
            $mid    = $read($midKey);
            $secret = $read($secretKey);
            if ($mid !== '' && $secret !== '') {
                $redactor = $redactor->withBasicAuthPair($mid, $secret, $secretKey);
            }
        }

        return $redactor;
    }

    /**
     * Minimal .env parser. Deliberately not a dependency, the file is a flat
     * list of KEY=VALUE lines with `#` comments.
     */
    public static function parseEnvFile(string $path): array
    {
        if (! is_readable($path)) {
            return [];
        }

        $values = [];

        foreach ((array) file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim((string) $line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $key   = trim($parts[0]);
            $value = trim($parts[1]);

            if (strlen($value) >= 2 && (
                ($value[0] === '"' && substr($value, -1) === '"')
                || ($value[0] === "'" && substr($value, -1) === "'")
            )) {
                $value = substr($value, 1, -1);
            }

            $values[$key] = $value;
        }

        return $values;
    }
}
