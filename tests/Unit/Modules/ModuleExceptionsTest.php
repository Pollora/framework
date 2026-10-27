<?php

declare(strict_types=1);

use Pollora\Modules\Domain\Exceptions\ModuleComponentException;
use Pollora\Modules\Domain\Exceptions\ModuleException;
use Pollora\Modules\Domain\Exceptions\ModuleStateException;

it('keeps a state refusal catchable as a RuntimeException, with its cause', function (): void {
    $previous = new LogicException('locked');
    $exception = ModuleStateException::because('Module demo is locked.', $previous);

    expect($exception)->toBeInstanceOf(RuntimeException::class)
        ->and($exception->getMessage())->toBe('Module demo is locked.')
        ->and($exception->getPrevious())->toBe($previous);
});

it('tells where module states come from when they cannot be changed', function (): void {
    expect(ModuleStateException::comesFromConfiguration()->getMessage())
        ->toContain('config/modules.php', 'MODULES_ENABLED');
});

it('says where the module states could not be written, and what to check', function (): void {
    $previous = new RuntimeException('disk full');

    expect(ModuleStateException::cannotWriteOption('pollora_modules', $previous)->getMessage())
        ->toBe('Cannot write the module states to the pollora_modules option: disk full')
        ->and(ModuleStateException::cannotWriteFile('/srv/modules.json')->getMessage())
        ->toStartWith('Cannot write the module states to /srv/modules.json.')
        ->toContain('writable');
});

it('names the component and the module of an initialisation failure', function (): void {
    $previous = new LogicException('boom');
    $exception = ModuleComponentException::initializationFailed('App\Component', 'demo', $previous);

    expect($exception)->toBeInstanceOf(RuntimeException::class)
        ->and($exception->getMessage())->toBe('Failed to initialize component App\Component for module demo: boom')
        ->and($exception->getPrevious())->toBe($previous);
});

it('says what failed in a module download, and what to check', function (): void {
    expect(ModuleException::repositoryUnreachable('acme/demo')->getMessage())->toStartWith('Unable to fetch repository information for acme/demo.')
        ->and(ModuleException::archiveNotDownloaded('https://x/y.zip')->getMessage())->toStartWith('Unable to download archive from https://x/y.zip.')
        ->and(ModuleException::archiveNotReadable('/tmp/a.zip')->getMessage())->toStartWith('Unable to open ZIP archive: /tmp/a.zip.')
        ->and(ModuleException::archiveEmpty('/tmp/x')->getMessage())->toStartWith('No extracted folder found in /tmp/x.');
});
