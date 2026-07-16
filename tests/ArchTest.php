<?php

declare(strict_types=1);

arch('it will not use debugging functions', function (): void {
    expect(['dd', 'dump', 'ray'])
        ->each->not->toBeUsed();
});
