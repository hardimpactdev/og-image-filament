<?php

declare(strict_types=1);

use HardImpact\OgImageFilament\Rendering\OgImageRenderer;
use Spatie\Browsershot\Browsershot;
use Workbench\App\Data\PostOgImageData;

it('renders a complete 1200 by 630 PNG document without launching Chrome', function (): void {
    config()->set('og-image-filament.node_binary', '/custom/node');
    config()->set('og-image-filament.chrome_path', '/custom/chrome');
    config()->set('og-image-filament.no_sandbox', true);

    $browser = new FakeBrowsershot;
    $html = null;
    $renderer = new OgImageRenderer(function (string $renderedHtml) use ($browser, &$html): Browsershot {
        $html = $renderedHtml;

        return $browser;
    });
    view()->addNamespace('test-og-images', dirname(__DIR__).'/Fixtures/views');

    expect($renderer->render(
        'test-og-images::source-card',
        new PostOgImageData('Rendered title'),
    ))->toBe('png-bytes')
        ->and($html)->toContain('<!DOCTYPE html>')
        ->and($html)->toContain('Rendered title')
        ->and($browser->window)->toBe([1200, 630])
        ->and($browser->nodeBinary)->toBe('/custom/node')
        ->and($browser->chromePath)->toBe('/custom/chrome')
        ->and($browser->sandboxDisabled)->toBeTrue()
        ->and($browser->savedPath)->not->toBeNull();

    if ($browser->savedPath === null) {
        throw new LogicException('The fake browser did not receive a target path.');
    }

    expect(file_exists($browser->savedPath))->toBeFalse();
});

final class FakeBrowsershot extends Browsershot
{
    /** @var array{int, int}|null */
    public ?array $window = null;

    public ?string $nodeBinary = null;

    public ?string $chromePath = null;

    public ?string $savedPath = null;

    public bool $sandboxDisabled = false;

    public function windowSize(int $width, int $height): static
    {
        $this->window = [$width, $height];

        return $this;
    }

    public function setNodeBinary(string $nodeBinary): static
    {
        $this->nodeBinary = $nodeBinary;

        return $this;
    }

    public function setChromePath(string $executablePath): static
    {
        $this->chromePath = $executablePath;

        return $this;
    }

    public function noSandbox(): static
    {
        $this->sandboxDisabled = true;

        return $this;
    }

    public function save(string $targetPath): void
    {
        $this->savedPath = $targetPath;
        file_put_contents($targetPath, 'png-bytes');
    }
}
