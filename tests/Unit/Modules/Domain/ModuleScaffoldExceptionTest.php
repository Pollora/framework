<?php

declare(strict_types=1);

use Pollora\Modules\Domain\Exceptions\ModuleScaffoldException;

it('names the working directory and says how to fix it when it cannot be created', function (): void {
    $exception = ModuleScaffoldException::cannotCreateWorkDirectory('/tmp/.pollora-scaffold-abc');

    expect($exception)->toBeInstanceOf(RuntimeException::class)
        ->and($exception->getMessage())->toContain('/tmp/.pollora-scaffold-abc')
        ->and($exception->getMessage())->toContain('temporary directory is writable');
});
