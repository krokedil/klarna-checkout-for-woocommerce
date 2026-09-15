<?php

declare(strict_types=1);

namespace Tests\Support\Extension;

use Codeception\Events;
use Codeception\Exception\ExtensionException;
use Codeception\Extension;
use Symfony\Component\Process\Process;

/**
 * Runs an ngrok HTTPS tunnel in front of BuiltInServerController for the lifetime
 * of a suite, which Kustom's SDK and API need and the built-in server cannot give.
 *
 * Requires `domain`, `publicUrl` and `forwardPort` in extensions.config. Optional:
 * `authtoken`, `waitForOnlineSeconds` (15), `apiUrl` (http://127.0.0.1:4040),
 * `siteUrl` (the URL the browser will use, health checked once the tunnel is up).
 *
 * The agent's own log is written to tests/_output/ngrok.log.
 */
class NgrokController extends Extension
{
    public static array $events = [
        Events::SUITE_BEFORE => 'start',
        Events::SUITE_AFTER  => 'stop',
        Events::TEST_BEFORE  => 'checkStillUp',
    ];

    private ?Process $process = null;

    public function start(): void
    {
        if ($this->process instanceof Process && $this->process->isRunning()) {
            // Already started for an earlier suite in the same run; reuse it.
            return;
        }

        // A process that has exited, or that we stopped, is not ours any more.
        $this->process = null;

        $domain      = (string) ($this->config['domain']      ?? '');
        $publicUrl   = (string) ($this->config['publicUrl']   ?? '');
        $forwardPort = (string) ($this->config['forwardPort'] ?? '');
        $authtoken   = (string) ($this->config['authtoken']   ?? '');
        $waitSeconds = (int)    ($this->config['waitForOnlineSeconds'] ?? 15);
        $apiUrl      = rtrim((string) ($this->config['apiUrl'] ?? 'http://127.0.0.1:4040'), '/');
        $siteUrl     = (string) ($this->config['siteUrl'] ?? '');

        if ($domain === '' || $publicUrl === '' || $forwardPort === '') {
            throw new ExtensionException(
                $this,
                'NgrokController requires domain, publicUrl, and forwardPort to be set in extensions.config.'
            );
        }

        // Reuse a tunnel that is already serving this public URL.
        if ($this->tunnelMatchingPublicUrl($apiUrl, $publicUrl) !== null) {
            $this->writeln("NgrokController: tunnel for {$publicUrl} already up; reusing it.");
            return;
        }
        if ($this->ngrokApiReachable($apiUrl)) {
            throw new ExtensionException(
                $this,
                "An ngrok process is already running on {$apiUrl} but is not serving {$publicUrl}. "
                . "Stop it (e.g. `pkill ngrok`) before running the test suite."
            );
        }

        $command = ['ngrok', 'http', '--domain=' . $domain, '--log=stdout', '--log-format=json', $forwardPort];
        $env     = [];
        if ($authtoken !== '') {
            $env['NGROK_AUTHTOKEN'] = $authtoken;
        }

        $this->writeln("NgrokController: starting `" . implode(' ', $command) . "` ...");

        $this->process = new Process($command, null, $env);
        $this->process->setTimeout(null);
        $this->process->setIdleTimeout(null);
        $this->process->start();

        $deadline = microtime(true) + $waitSeconds;
        $lastErr  = '';
        while (microtime(true) < $deadline) {
            if (! $this->process->isRunning()) {
                throw new ExtensionException(
                    $this,
                    "ngrok exited before reaching `online` (code " . $this->process->getExitCode() . "). "
                    . "stderr: " . trim($this->process->getErrorOutput()) . ", "
                    . "stdout: " . trim($this->process->getOutput())
                );
            }
            if ($this->tunnelMatchingPublicUrl($apiUrl, $publicUrl) !== null) {
                $this->writeln("NgrokController: tunnel online at {$publicUrl}.");
                $this->waitForSiteToAnswer($siteUrl, $waitSeconds);
                return;
            }
            $lastErr = (string) $this->process->getErrorOutput();
            usleep(250_000); // 250ms
        }

        // Timeout. Kill it and report what we saw.
        $stderr = trim($lastErr ?: $this->process->getErrorOutput());
        $stdout = trim($this->process->getOutput());
        $this->stop();
        throw new ExtensionException(
            $this,
            "ngrok did not expose {$publicUrl} within {$waitSeconds}s. "
            . ($stderr !== '' ? "stderr: {$stderr}, " : '')
            . ($stdout !== '' ? "stdout: {$stdout}" : '')
        );
    }

    /**
     * Restarts the tunnel between tests when it, or the site behind it, has stopped
     * answering. A stalled agent keeps running but hangs the traffic through it.
     */
    public function checkStillUp(): void
    {
        if (! $this->process instanceof Process) {
            return;
        }

        $siteUrl = (string) ($this->config['siteUrl'] ?? '');

        if ($this->process->isRunning() && ($siteUrl === '' || $this->answers($siteUrl))) {
            return;
        }

        $this->writeln('NgrokController: the tunnel stopped answering, restarting it ...');
        $this->flushAgentLog();
        $this->stop();
        $this->start();
    }

    /** Whether $url answers anything at all, quickly. */
    private function answers(string $url): bool
    {
        $status = $this->statusOf($url, 5);

        return $status > 0 && $status < 400;
    }

    /**
     * Waits for the public URL the browser will use to answer, which an online tunnel
     * does not guarantee: the agent binds the internal endpoint, not the public one.
     */
    private function waitForSiteToAnswer(string $siteUrl, int $waitSeconds): void
    {
        if ($siteUrl === '') {
            return;
        }

        $deadline = microtime(true) + $waitSeconds;
        $status   = 0;

        while (microtime(true) < $deadline) {
            $status = $this->statusOf($siteUrl);
            if ($status > 0 && $status < 400) {
                $this->writeln("NgrokController: {$siteUrl} answers {$status}.");
                return;
            }
            usleep(500_000); // 500ms
        }

        $this->flushAgentLog();

        throw new ExtensionException(
            $this,
            "The tunnel is up, but {$siteUrl} answers " . ($status === 0 ? 'nothing' : (string) $status)
            . ". Chrome would see the same and every test would fail on a browser error page. An empty-bodied 403"
            . ' means nothing is bound to the public endpoint: check that this run owns the only ngrok agent, and'
            . ' read ' . $this->logPath() . '.'
        );
    }

    /** The status code $url answers with, or 0 when it answers nothing at all. */
    private function statusOf(string $url, int $timeout = 10): int
    {
        $context = stream_context_create(
            [
                'http' => [
                    'method'          => 'GET',
                    'timeout'         => $timeout,
                    'ignore_errors'   => true,
                    'follow_location' => 0,
                ],
            ]
        );

        $body = @file_get_contents($url, false, $context);
        if ($body === false && ! isset($http_response_header)) {
            return 0;
        }

        $headers = $http_response_header ?? [];
        if (! isset($headers[0]) || preg_match('#\s(\d{3})\s#', $headers[0], $matches) !== 1) {
            return 0;
        }

        return (int) $matches[1];
    }

    private function logPath(): string
    {
        return codecept_output_dir('ngrok.log');
    }

    /** Writes whatever the agent has said so far to tests/_output/ngrok.log. */
    private function flushAgentLog(): void
    {
        if (! $this->process instanceof Process) {
            return;
        }

        $log = trim($this->process->getOutput() . "\n" . $this->process->getErrorOutput());
        if ($log === '') {
            return;
        }

        // Appended, so a restart keeps the log of the agent it replaced.
        file_put_contents($this->logPath(), $log . "\n", FILE_APPEND);
    }

    public function stop(): void
    {
        if (! $this->process instanceof Process) {
            return;
        }
        $this->flushAgentLog();

        if ($this->process->isRunning()) {
            $this->writeln('NgrokController: stopping tunnel ...');
            $this->process->stop(5.0, defined('SIGTERM') ? SIGTERM : 15);
        }
        $this->process = null;
    }

    private function ngrokApiReachable(string $apiUrl): bool
    {
        $ctx = stream_context_create(['http' => ['timeout' => 1, 'ignore_errors' => true]]);
        $body = @file_get_contents($apiUrl . '/api/tunnels', false, $ctx);
        return $body !== false;
    }

    private function tunnelMatchingPublicUrl(string $apiUrl, string $expectedPublicUrl): ?array
    {
        $ctx = stream_context_create(['http' => ['timeout' => 1, 'ignore_errors' => true]]);
        $body = @file_get_contents($apiUrl . '/api/tunnels', false, $ctx);
        if ($body === false) {
			echo "NgrokController: could not reach ngrok API at {$apiUrl} to check tunnels.\n";
            return null;
        }

        $data = json_decode($body, true);
        if (! is_array($data) || ! isset($data['tunnels']) || ! is_array($data['tunnels'])) {
			echo "NgrokController: unexpected response from ngrok API at {$apiUrl} when checking tunnels: {$body}\n";
            return null;
        }

        foreach ($data['tunnels'] as $tunnel) {
            $public = $tunnel['public_url'] ?? '';
            $proto  = $tunnel['proto']      ?? '';
            if ($public === $expectedPublicUrl && $proto === 'https') {
                return $tunnel;
            }
        }

        return null;
    }
}
