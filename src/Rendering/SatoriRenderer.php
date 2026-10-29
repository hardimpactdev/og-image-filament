<?php

declare(strict_types=1);

namespace HardImpact\OgImageFilament\Rendering;

use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/** Runs a registered, code-owned template. Record data supplies text only. */
final readonly class SatoriRenderer
{
    private const array LIMITS = ['label' => 80, 'title' => 512, 'description' => 2000, 'url' => 2048];

    private const int MAX_PNG_BYTES = 4 * 1024 * 1024;

    public function render(string $view, object $data): string
    {
        $templates = config('og-image-filament.satori.templates');
        $definition = is_array($templates) ? ($templates[$view] ?? null) : null;

        if (! is_array($definition) || ! is_string($definition['directory'] ?? null) || ! is_string($definition['template'] ?? null)) {
            throw new RuntimeException('The browser-free OG template is not registered.');
        }

        $directory = realpath($definition['directory']);
        $modules = config('og-image-filament.satori.node_modules');
        $modules = is_string($modules) ? realpath($modules) : false;
        $timeout = config('og-image-filament.satori.timeout_seconds', 10);

        if ($directory === false || ! is_dir($directory) || $modules === false || ! is_dir($modules)
            || ! is_int($timeout) || $timeout < 1 || $timeout > 30
            || preg_match('/\A[a-z0-9][a-z0-9-]{0,63}\z/', $definition['template']) !== 1) {
            throw new RuntimeException('Invalid browser-free OG configuration.');
        }

        $values = get_object_vars($data);

        if (array_diff(array_keys($values), array_keys(self::LIMITS)) !== []) {
            throw new RuntimeException('OG image data accepts only label, title, description and url text.');
        }

        $text = [];
        foreach (self::LIMITS as $field => $limit) {
            $value = $values[$field] ?? '';
            if (! is_string($value) || ! mb_check_encoding($value, 'UTF-8') || mb_strlen($value) > $limit
                || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) === 1) {
                throw new RuntimeException("Invalid OG image text [{$field}].");
            }
            $text[$field] = $value;
        }

        $input = json_encode($text, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        if (strlen($input) > 16 * 1024) {
            throw new RuntimeException('OG image text exceeds the input budget.');
        }

        $node = config('og-image-filament.node_binary');
        $node = is_string($node) && $node !== '' ? $node : (new ExecutableFinder)->find('node');
        if ($node === null || ! is_file($node) || ! is_executable($node)) {
            throw new RuntimeException('A Node.js 24+ executable is required for browser-free OG images.');
        }

        $entry = dirname(__DIR__, 2).'/resources/node/render.mjs';
        $command = [$node, '--no-addons', '--permission', '--max-old-space-size=256'];
        foreach (array_unique([dirname($entry), $directory, $modules]) as $readable) {
            $command[] = '--allow-fs-read='.$readable;
        }
        array_push($command, $entry, $directory, $definition['template'], $modules);

        // Symfony otherwise merges the parent's environment, including app credentials.
        $inherited = array_unique([...array_keys(getenv()), ...array_keys($_ENV), ...array_keys($_SERVER)]);
        $environment = array_fill_keys($inherited, false);
        $environment['PATH'] = dirname($node).PATH_SEPARATOR.'/usr/bin'.PATH_SEPARATOR.'/bin';
        if (PHP_OS_FAMILY === 'Windows') {
            $environment['SystemRoot'] = getenv('SystemRoot') ?: 'C:\\Windows';
        }

        $lockPath = storage_path('framework/cache/og-image-render.lock');
        $lock = fopen($lockPath, 'c');
        if ($lock === false) {
            throw new RuntimeException('The OG image render lock could not be opened.');
        }
        $deadline = microtime(true) + $timeout;
        $process = null;

        try {
            while (! flock($lock, LOCK_EX | LOCK_NB)) {
                if (microtime(true) >= $deadline) {
                    throw new RuntimeException('Timed out waiting for the OG image renderer.');
                }
                usleep(10_000);
            }

            $process = new Process($command, $directory, $environment, $input, max(0.001, $deadline - microtime(true)));
            $png = '';
            $errors = '';
            $exceeded = false;
            // Do not throw from a Process callback: status/stop can invoke it again.
            $process->start(function (string $type, string $buffer) use (&$png, &$errors, &$exceeded): void {
                if ($type === Process::OUT) {
                    $room = self::MAX_PNG_BYTES - strlen($png);
                    $png .= substr($buffer, 0, $room);
                } else {
                    $room = 8192 - strlen($errors);
                    $errors .= substr($buffer, 0, $room);
                }
                if (strlen($buffer) > $room) {
                    $exceeded = true;
                }
            });

            while ($process->isRunning()) {
                if ($exceeded) {
                    $process->stop(0);
                    break;
                }
                $process->checkTimeout();
                $process->clearOutput();
                $process->clearErrorOutput();
                usleep(1_000);
            }

            if ($exceeded) {
                throw new RuntimeException('The OG renderer exceeded its output budget.');
            }

            if (! $process->isSuccessful()) {
                throw new RuntimeException('The browser-free OG renderer failed: '.mb_substr(trim($errors), 0, 1000));
            }

            if (strlen($png) < 24 || substr($png, 0, 8) !== "\x89PNG\r\n\x1a\n"
                || substr($png, 12, 4) !== 'IHDR' || unpack('Nwidth/Nheight', substr($png, 16, 8)) !== ['width' => 1200, 'height' => 630]) {
                throw new RuntimeException('The OG renderer did not return a 1200 by 630 PNG.');
            }

            return $png;
        } finally {
            try {
                if ($process?->isRunning()) {
                    $process->stop(0);
                }
            } finally {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }
}
