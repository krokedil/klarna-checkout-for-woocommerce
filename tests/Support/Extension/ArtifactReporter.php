<?php

declare(strict_types=1);

namespace Tests\Support\Extension;

use Codeception\Event\FailEvent;
use Codeception\Event\TestEvent;
use Codeception\Events;
use Codeception\Extension;
use Codeception\Test\Descriptor;
use Qameta\Allure\Allure;
use Tests\Support\Reporting\Redactor;
use Tests\Support\Reporting\SecretRegistry;

/**
 * Attaches browser artifacts to failed EndToEnd tests: the screenshot and page
 * source WPWebDriver already writes, plus the browser console log and a summary
 * of what the browser fetched.
 *
 * Everything textual is scrubbed through the Redactor first, because these artifacts are
 * downloadable from CI and the E2E suite runs against real Kustom test credentials.
 *
 * Chrome's logs are read on TEST_FAIL rather than TEST_AFTER: the suite sets `restart: true`,
 * so the driver is already nulled by TEST_AFTER. Attaching happens on TEST_AFTER, after
 * WPWebDriver has written its files and before Allure flushes the result.
 */
class ArtifactReporter extends Extension
{
    public static array $events = [
        Events::TEST_FAIL  => 'markFailed',
        Events::TEST_ERROR => 'markFailed',
        Events::TEST_AFTER => 'attach',
    ];

    /**
     * The WPWebDriver module, keyed as it is written in the suite config.
     * Codeception keys modules by that exact string, so the short name
     * `WPWebDriver` does not resolve here.
     */
    private const WEBDRIVER_MODULE = 'lucatume\WPBrowser\Module\WPWebDriver';

    private array $failed = [];

    /**
     * Chrome log entries harvested at TEST_FAIL, keyed by test signature then
     * log type. Held only until the matching TEST_AFTER consumes them.
     *
     * @var array<string, array<string, array<int, array>>>
     */
    private array $logs = [];

    private ?Redactor $redactor = null;

    public function markFailed(FailEvent $event): void
    {
        $signature = Descriptor::getTestSignatureUnique($event->getTest());

        $this->failed[$signature] = true;

        // Last chance to talk to the browser, see the class docblock.
        $this->logs[$signature] = [
            'browser'     => $this->readChromeLog('browser'),
            'performance' => $this->readChromeLog('performance'),
        ];
    }

    public function attach(TestEvent $event): void
    {
        $signature = Descriptor::getTestSignatureUnique($event->getTest());

        if (! isset($this->failed[$signature])) {
            return;
        }
        unset($this->failed[$signature]);

        $logs = $this->logs[$signature] ?? [];
        unset($this->logs[$signature]);

        $this->attachWebDriverReports($event);
        $this->attachConsoleLog($logs['browser'] ?? []);
        $this->attachNetworkSummary($logs['performance'] ?? []);
    }

    /**
     * The .fail.png and .fail.html WPWebDriver wrote, via the paths it
     * registered on the test's metadata.
     */
    private function attachWebDriverReports(TestEvent $event): void
    {
        $reports = $event->getTest()->getMetadata()->getReports();

        if (isset($reports['png']) && is_readable($reports['png'])) {
            // Binary, nothing to scrub, and nothing we could scrub.
            Allure::attachmentFile('screenshot.png', $reports['png'], 'image/png');
        }

        if (isset($reports['html']) && is_readable($reports['html'])) {
            $source   = (string) file_get_contents($reports['html']);
            $scrubbed = $this->redactor()->scrub($source);

            if ($scrubbed !== $source) {
                file_put_contents($reports['html'], $scrubbed);
            }

            Allure::attachment('page-source.html', $scrubbed, 'text/html');
        }
    }

    /**
     * Chrome's console log. The gateway's SDK reports iframe and tokenisation
     * failures here and nowhere else, which is usually the real reason a
     * checkout test failed.
     */
    private function attachConsoleLog(array $entries): void
    {
        if ($entries === []) {
            return;
        }

        $lines = [];
        foreach ($entries as $entry) {
            $lines[] = sprintf(
                '[%s] %s',
                $entry['level'] ?? 'INFO',
                $entry['message'] ?? ''
            );
        }

        Allure::attachment(
            'browser-console.log',
            $this->redactor()->scrub(implode("\n", $lines)),
            'text/plain'
        );
    }

    /**
     * A compact list of what the browser fetched, distilled from Chrome's
     * performance log.
     */
    private function attachNetworkSummary(array $entries): void
    {
        $requests = [];

        foreach ($entries as $entry) {
            $payload = json_decode((string) ($entry['message'] ?? ''), true);
            $message = $payload['message'] ?? null;

            if (! is_array($message)) {
                continue;
            }

            $method = $message['method'] ?? '';
            $params = $message['params'] ?? [];
            $id     = $params['requestId'] ?? null;

            if (! is_string($id)) {
                continue;
            }

            if ($method === 'Network.requestWillBeSent') {
                $requests[$id]['method'] = $params['request']['method'] ?? '';
                $requests[$id]['url']    = $params['request']['url'] ?? '';
            }

            if ($method === 'Network.responseReceived') {
                $requests[$id]['status']   = $params['response']['status'] ?? null;
                $requests[$id]['mimeType'] = $params['response']['mimeType'] ?? '';
            }

            if ($method === 'Network.loadingFailed') {
                $requests[$id]['status'] = 'failed: ' . ($params['errorText'] ?? 'unknown');
            }
        }

        if ($requests === []) {
            return;
        }

        // Drop data: URIs, inlined images bury the requests that matter.
        $rows = array_values(array_filter(
            $requests,
            static fn(array $row): bool => ! str_starts_with((string) ($row['url'] ?? ''), 'data:')
        ));

        $json = json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (! is_string($json)) {
            return;
        }

        Allure::attachment('network.json', $this->redactor()->scrub($json), 'application/json');
    }

    /** Reads one of Chrome's logs. */
    private function readChromeLog(string $type): array
    {
        if (! $this->hasModule(self::WEBDRIVER_MODULE)) {
            return [];
        }

        $webDriver = $this->getModule(self::WEBDRIVER_MODULE)->webDriver ?? null;
        if ($webDriver === null) {
            return [];
        }

        try {
            $server  = $webDriver->getCommandExecutor()->getAddressOfRemoteServer();
            $session = $webDriver->getSessionID();
        } catch (\Throwable $e) {
            $this->writeln("ArtifactReporter: no browser session to read the {$type} log from, " . $e->getMessage());
            return [];
        }

        $response = $this->post(rtrim((string) $server, '/') . '/session/' . $session . '/se/log', ['type' => $type]);

        if (! is_array($response['value'] ?? null)) {
            return [];
        }

        return array_values(array_filter($response['value'], 'is_array'));
    }

    /**
     * Minimal JSON POST. Deliberately not a dependency: this is one call to a
     * driver already listening on localhost.
     */
    private function post(string $url, array $body): array
    {
        $context = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => "Content-Type: application/json\r\n",
                'content'       => (string) json_encode($body),
                'timeout'       => 10,
                'ignore_errors' => true,
            ],
        ]);

        $raw = @file_get_contents($url, false, $context);
        if ($raw === false) {
            $this->writeln('ArtifactReporter: could not reach ChromeDriver at ' . $url);
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function redactor(): Redactor
    {
        return $this->redactor ??= SecretRegistry::fromEnvironment();
    }
}
