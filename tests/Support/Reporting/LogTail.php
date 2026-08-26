<?php

declare(strict_types=1);

namespace Tests\Support\Reporting;

/**
 * Reads just the part of WordPress's and WooCommerce's log files that a single
 * test produced.
 *
 * The site appends to debug.log and to a file per handle under wc-logs/, across tests and
 * across runs, so this records where each file ended when a test started and hands back only
 * what arrived after.
 *
 * Deliberately free of Codeception, Allure and WordPress: WordPressLogReporter owns the event
 * wiring, this owns the bookkeeping, and the bookkeeping is the part worth testing.
 */
final class LogTail
{
    /**
     * Per-attachment ceiling. A notice raised inside a WordPress hook fires
     * once per request, so a single test can otherwise produce megabytes of
     * identical lines.
     */
    public const MAX_ATTACHMENT_BYTES = 2097152;

    /**
     * WooCommerce names every log `<handle>-<date>-<32 hex>.log`. The suffix is
     * rotation bookkeeping and only makes the attachment name unreadable.
     */
    private const WC_ROTATION_SUFFIX = '/-\d{4}-\d{2}-\d{2}-[a-f0-9]{32}\.log$/';

    private string $debugLog;

    private string $wcLogDir;

    private array $marks = [];

    public function __construct(string $wpRootFolder)
    {
        $root = rtrim($wpRootFolder, '/');

        $this->debugLog = $root . '/wp-content/debug.log';
        $this->wcLogDir = $root . '/wp-content/uploads/wc-logs';
    }

    /**
     * Throws away everything previous runs left behind. debug.log is truncated rather than
     * deleted so it keeps the ownership the web server gave it.
     */
    public function clear(): void
    {
        if (is_file($this->debugLog)) {
            file_put_contents($this->debugLog, '');
        }

        foreach ($this->wcLogFiles() as $path) {
            @unlink($path);
        }

        $this->marks = [];
    }

    /** Records where every log currently ends. */
    public function mark(): void
    {
        $this->marks = [];

        foreach ($this->allLogFiles() as $path) {
            $this->marks[$path] = $this->sizeOf($path);
        }
    }

    /** Everything written since mark(), keyed by attachment name. */
    public function delta(): array
    {
        $deltas = [];

        foreach ($this->allLogFiles() as $path) {
            $content = $this->tailFrom($path, $this->marks[$path] ?? 0);

            if ($content !== '') {
                $deltas[$this->labelFor($path)] = $content;
            }
        }

        return $deltas;
    }

    private function allLogFiles(): array
    {
        return array_merge([$this->debugLog], $this->wcLogFiles());
    }

    private function wcLogFiles(): array
    {
        return glob($this->wcLogDir . '/*.log') ?: [];
    }

    private function sizeOf(string $path): int
    {
        if (! is_file($path)) {
            return 0;
        }

        clearstatcache(true, $path);

        return (int) filesize($path);
    }

    private function tailFrom(string $path, int $offset): string
    {
        $size = $this->sizeOf($path);

        if ($size === $offset) {
            return '';
        }

        // Smaller than the mark means the file was rotated or cleared, so read it whole.
        $from = $size < $offset ? 0 : $offset;

        $content = @file_get_contents($path, false, null, $from);

        return is_string($content) ? $this->cap($content) : '';
    }

    private function cap(string $content): string
    {
        if (strlen($content) <= self::MAX_ATTACHMENT_BYTES) {
            return $content;
        }

        return substr($content, 0, self::MAX_ATTACHMENT_BYTES)
            . sprintf("\n\n[truncated at %d bytes by LogTail]\n", self::MAX_ATTACHMENT_BYTES);
    }

    private function labelFor(string $path): string
    {
        return (string) preg_replace(self::WC_ROTATION_SUFFIX, '.log', basename($path));
    }
}
