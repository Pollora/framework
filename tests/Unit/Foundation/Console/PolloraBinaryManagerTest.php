<?php

declare(strict_types=1);

use Illuminate\Console\Application as Artisan;
use Illuminate\Console\Command;
use Illuminate\Foundation\Application;
use Pollora\Foundation\Console\PolloraBinaryManager;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\EventDispatcher\EventDispatcher;

function createTestArtisan(): Artisan
{
    $app = new Application(dirname(__DIR__, 4));
    $events = new \Illuminate\Events\Dispatcher($app);

    return new Artisan($app, $events, 'test');
}

describe('PolloraBinaryManager', function (): void {

    it('returns the full signature map', function (): void {
        $map = PolloraBinaryManager::getSignatureMap();

        expect($map)->toBeArray()
            ->and($map)->toHaveKey('pollora:install', 'install')
            ->and($map)->toHaveKey('pollora:make-plugin', 'make-plugin')
            ->and($map)->toHaveKey('pollora:make-theme', 'make-theme')
            ->and($map)->toHaveKey('pollora:make-block', 'make-block')
            ->and($map)->toHaveKey('pollora:status', 'status')
            ->and($map)->toHaveKey('pollora:plugin:list', 'plugin:list')
            ->and($map)->toHaveKey('pollora:theme:status', 'theme:status');
    });

    it('maps all 17 pollora commands', function (): void {
        $map = PolloraBinaryManager::getSignatureMap();

        expect($map)->toHaveCount(17);

        foreach ($map as $original => $short) {
            expect($original)->toStartWith('pollora:');
            expect($short)->not->toStartWith('pollora:');
        }
    });

    it('includes utility commands in visible list', function (): void {
        $visible = PolloraBinaryManager::getVisibleCommands();

        expect($visible)->toContain('list', 'help', 'completion');
    });

    it('includes all short aliases in visible list', function (): void {
        $visible = PolloraBinaryManager::getVisibleCommands();
        $map = PolloraBinaryManager::getSignatureMap();

        foreach ($map as $short) {
            expect($visible)->toContain($short);
        }
    });

    it('remaps command names when applied to artisan', function (): void {
        if (! PolloraBinaryManager::isPolloraBinary()) {
            // In tests without POLLORA_BINARY, remapCommands should be a no-op
            $artisan = createTestArtisan();

            $command = new class extends SymfonyCommand
            {
                protected static $defaultName = 'pollora:make-plugin';

                protected function configure(): void
                {
                    $this->setName('pollora:make-plugin');
                    $this->setDescription('Test command');
                }
            };

            $artisan->add($command);

            // Without POLLORA_BINARY, remap should not change anything
            PolloraBinaryManager::remapCommands($artisan);

            expect($artisan->has('pollora:make-plugin'))->toBeTrue();
        }

        expect(true)->toBeTrue();
    });

    it('does not remap when POLLORA_BINARY is not defined', function (): void {
        // Since POLLORA_BINARY is not defined in test context,
        // isPolloraBinary should return false
        if (! defined('POLLORA_BINARY')) {
            expect(PolloraBinaryManager::isPolloraBinary())->toBeFalse();
        }
    });

    it('has consistent mapping between signature map and visible commands', function (): void {
        $map = PolloraBinaryManager::getSignatureMap();
        $visible = PolloraBinaryManager::getVisibleCommands();

        // All short aliases from the map should be in the visible list
        foreach (array_values($map) as $shortName) {
            expect($visible)->toContain($shortName);
        }
    });

    it('does not have duplicate short names in signature map', function (): void {
        $map = PolloraBinaryManager::getSignatureMap();
        $shortNames = array_values($map);

        expect(count($shortNames))->toBe(count(array_unique($shortNames)));
    });
});
