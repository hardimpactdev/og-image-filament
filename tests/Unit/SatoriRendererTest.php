<?php

declare(strict_types=1);

use HardImpact\OgImageFilament\Rendering\OgImageRenderer;
use Symfony\Component\Process\Exception\ProcessTimedOutException;

beforeEach(function (): void {
    config()->set('og-image-filament.renderer', 'satori');
    config()->set('og-image-filament.satori.templates', [
        'test-card' => ['directory' => dirname(__DIR__).'/Fixtures/satori', 'template' => 'card'],
    ]);
    config()->set('og-image-filament.satori.node_modules', dirname(__DIR__, 2).'/node_modules');
    config()->set('og-image-filament.satori.timeout_seconds', 3);
});

it('renders a real PNG without Blade, Chrome, secrets or ambient permissions', function (string $title): void {
    putenv('OG_TEST_SECRET=must-not-reach-the-child');
    $_ENV['OG_TEST_SECRET'] = $_SERVER['OG_TEST_SECRET'] = 'must-not-reach-the-child';
    try {
        $png = (new OgImageRenderer)->render('test-card', (object) ['title' => $title]);
        expect(substr($png, 0, 8))->toBe("\x89PNG\r\n\x1a\n")
            ->and(unpack('Nwidth/Nheight', substr($png, 16, 8)))->toBe(['width' => 1200, 'height' => 630]);
    } finally {
        putenv('OG_TEST_SECRET');
        unset($_ENV['OG_TEST_SECRET'], $_SERVER['OG_TEST_SECRET']);
    }
})->with(['Hello, café & <safe>', 'permissions']);

it('refuses an unknown template instead of falling back to a browser', function (): void {
    (new OgImageRenderer)->render('missing', (object) ['title' => 'Title']);
})->throws(RuntimeException::class, 'not registered');

it('rejects non-text fields, extra fields and excessive text before starting Node', function (array $data): void {
    (new OgImageRenderer)->render('test-card', (object) $data);
})->with([
    'array' => [['title' => ['nested']]],
    'path' => [['title' => 'Title', 'background' => '/etc/passwd']],
    'long title' => [['title' => str_repeat('a', 513)]],
    'control' => [['title' => "a\0b"]],
    'invalid UTF-8' => [['title' => "\xff"]],
])->throws(RuntimeException::class);

it('kills a stalled renderer and releases the lock for the next render', function (): void {
    config()->set('og-image-filament.satori.timeout_seconds', 1);
    try {
        (new OgImageRenderer)->render('test-card', (object) ['title' => 'timeout']);
        throw new LogicException('Expected a timeout.');
    } catch (ProcessTimedOutException) {
        config()->set('og-image-filament.satori.timeout_seconds', 3);
        expect((new OgImageRenderer)->render('test-card', (object) ['title' => 'Recovered']))
            ->toStartWith("\x89PNG\r\n\x1a\n");
    }
});

it('refuses a successful process that returned invalid image bytes', function (): void {
    (new OgImageRenderer)->render('test-card', (object) ['title' => 'invalid-output']);
})->throws(RuntimeException::class, '1200 by 630 PNG');

it('shares the deadline with the per-Instance render lock', function (): void {
    config()->set('og-image-filament.satori.timeout_seconds', 1);
    $lock = fopen(storage_path('framework/cache/og-image-render.lock'), 'c');
    if ($lock === false) {
        throw new LogicException('Could not open test lock.');
    }
    flock($lock, LOCK_EX);
    try {
        (new OgImageRenderer)->render('test-card', (object) ['title' => 'Locked']);
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
})->throws(RuntimeException::class, 'Timed out waiting');

it('rejects unknown renderer configuration', function (): void {
    config()->set('og-image-filament.renderer', 'typo');
    (new OgImageRenderer)->render('test-card', (object) ['title' => 'Title']);
})->throws(RuntimeException::class, 'Unknown OG image renderer');

it('fails closed for missing bundled files and unknown manifest IDs', function (string $template): void {
    config()->set('og-image-filament.satori.templates.test-card.template', $template);
    (new OgImageRenderer)->render('test-card', (object) ['title' => 'Title']);
})->with(['missing', 'missing-background', 'outside-background'])->throws(RuntimeException::class);

it('stops a template that exceeds the output budget and releases its lock', function (): void {
    config()->set('og-image-filament.satori.timeout_seconds', 10);
    try {
        (new OgImageRenderer)->render('test-card', (object) ['title' => 'oversized-output']);
        throw new LogicException('Expected an output budget failure.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toContain('output budget');
    }
    expect((new OgImageRenderer)->render('test-card', (object) ['title' => 'Recovered']))
        ->toStartWith("\x89PNG\r\n\x1a\n");
});

it('rejects a bundled background before reading beyond its byte budget', function (): void {
    $directory = dirname(__DIR__, 2).'/build/asset-budget-'.bin2hex(random_bytes(4));
    mkdir($directory, 0700, true);
    file_put_contents($directory.'/manifest.json', '{"templates":{"card":{"background":"large.png"}},"fonts":[{"name":"Fixture","file":"font.ttf"}]}');
    file_put_contents($directory.'/large.png', str_repeat('x', 3 * 1024 * 1024 + 1));
    config()->set('og-image-filament.satori.templates.test-card.directory', $directory);
    try {
        (new OgImageRenderer)->render('test-card', (object) ['title' => 'Title']);
    } finally {
        unlink($directory.'/manifest.json');
        unlink($directory.'/large.png');
        rmdir($directory);
    }
})->throws(RuntimeException::class, 'size budget');
