<?php

declare(strict_types=1);

namespace Tests\Support\Extension;

use Codeception\Event\SuiteEvent;
use Codeception\Event\TestEvent;
use Codeception\Events;
use Codeception\Extension;
use Qameta\Allure\Allure;
use Tests\Support\Reporting\LogTail;
use Tests\Support\Reporting\Redactor;
use Tests\Support\Reporting\SecretRegistry;

/**
 * Attaches what the WordPress install under test wrote, `wp-content/debug.log`
 * and WooCommerce's `wc-logs/` files, to the test that provoked it.
 *
 * The counterpart to ArtifactReporter: that one carries what the browser saw, this one what
 * the server wrote. It runs for passing tests too, because a green checkout can still raise a
 * deprecation. Logs are cleared once per suite, and LogTail keeps each test to its own lines.
 */
class WordPressLogReporter extends Extension
{
    public static array $events = [
        Events::SUITE_BEFORE => 'clearLogs',
        Events::TEST_BEFORE  => 'markLogs',
        Events::TEST_AFTER   => 'attachLogs',
    ];

    private ?LogTail $tail = null;

    private ?Redactor $redactor = null;

    public function clearLogs(SuiteEvent $event): void
    {
        $this->tail()?->clear();
    }

    public function markLogs(TestEvent $event): void
    {
        $this->tail()?->mark();
    }

    public function attachLogs(TestEvent $event): void
    {
        $tail = $this->tail();

        if ($tail === null) {
            return;
        }

        foreach ($tail->delta() as $name => $content) {
            Allure::attachment($name, $this->redactor()->scrub($content), 'text/plain');
        }
    }

    /**
     * Null when the extension is misconfigured, which is reported once rather
     * than on every test.
     */
    private function tail(): ?LogTail
    {
        if ($this->tail instanceof LogTail) {
            return $this->tail;
        }

        $root = (string) ($this->config['wpRootFolder'] ?? '');

        if ($root === '') {
            $this->writeln('WordPressLogReporter: no wpRootFolder configured, not collecting logs.');
            return null;
        }

        // WORDPRESS_ROOT_DIR is written relative to the project root.
        if (! str_starts_with($root, '/')) {
            $root = rtrim($this->getRootDir(), '/') . '/' . $root;
        }

        if (! is_dir($root)) {
            $this->writeln("WordPressLogReporter: {$root} is not a directory, not collecting logs.");
            return null;
        }

        return $this->tail = new LogTail($root);
    }

    private function redactor(): Redactor
    {
        return $this->redactor ??= SecretRegistry::fromEnvironment();
    }
}
