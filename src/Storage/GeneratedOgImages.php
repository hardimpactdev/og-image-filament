<?php

declare(strict_types=1);

namespace HardImpact\OgImageFilament\Storage;

use Closure;
use Illuminate\Contracts\Filesystem\Cloud;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;
use Throwable;

final readonly class GeneratedOgImages
{
    /**
     * @param  null|Closure(Cloud, string, string): bool  $writer
     * @param  null|Closure(Cloud, string): bool  $deleter
     */
    public function __construct(
        private FilesystemManager $filesystems,
        private ?Closure $writer = null,
        private ?Closure $deleter = null,
    ) {}

    public function replace(string $path, string $png): void
    {
        $disk = $this->disk();
        $finalPath = $this->fullPath($path);
        $temporaryPath = $finalPath.'.'.Str::uuid()->toString().'.tmp';

        try {
            $written = $this->writer === null
                ? $disk->put($temporaryPath, $png)
                : ($this->writer)($disk, $temporaryPath, $png);

            if (! $written) {
                throw new RuntimeException("The generated OG image could not be written to [{$temporaryPath}].");
            }

            if (! $disk->move($temporaryPath, $finalPath)) {
                throw new RuntimeException("The generated OG image could not replace [{$finalPath}].");
            }
        } catch (Throwable $exception) {
            $disk->delete($temporaryPath);

            throw $exception;
        }
    }

    public function url(string $path): ?string
    {
        $disk = $this->disk();
        $fullPath = $this->fullPath($path);

        if (! $disk->exists($fullPath)) {
            return null;
        }

        return $disk->url($fullPath);
    }

    public function delete(string $path): void
    {
        $disk = $this->disk();
        $fullPath = $this->fullPath($path);

        if (! $disk->exists($fullPath)) {
            return;
        }

        $deleted = $this->deleter === null
            ? $disk->delete($fullPath)
            : ($this->deleter)($disk, $fullPath);

        if (! $deleted) {
            throw new RuntimeException("The generated OG image [{$fullPath}] could not be deleted.");
        }
    }

    private function disk(): Cloud
    {
        $disk = $this->filesystems->disk(config()->string('og-image-filament.disk'));

        if (! $disk instanceof Cloud) {
            throw new LogicException('The configured OG image disk must support public URLs.');
        }

        return $disk;
    }

    private function fullPath(string $path): string
    {
        $directory = trim(config()->string('og-image-filament.directory'), '/');
        $path = ltrim($path, '/');

        return $directory === '' ? $path : "{$directory}/{$path}";
    }
}
