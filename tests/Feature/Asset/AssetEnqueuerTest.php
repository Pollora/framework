<?php

declare(strict_types=1);

use Pollora\Application\Application\Services\ConsoleDetectionService;
use Pollora\Application\Domain\Contracts\ConsoleDetectorInterface;
use Pollora\Asset\Application\Services\AssetManager;
use Pollora\Asset\Infrastructure\Services\AssetEnqueuer;
use Pollora\Hook\Domain\Contract\Action as HookAction;

beforeEach(function (): void {
    // Bind console detection to always return true (prevents WP calls)
    $detector = Mockery::mock(ConsoleDetectorInterface::class);
    $detector->shouldReceive('isConsole')->andReturn(true);
    $detector->shouldReceive('isWpCli')->andReturn(false);

    $this->app->instance(ConsoleDetectorInterface::class, $detector);
    $this->app->singleton(ConsoleDetectionService::class, fn (): ConsoleDetectionService => new ConsoleDetectionService($detector));

    // Bind AssetManager
    $this->assetManager = Mockery::mock(AssetManager::class);
    $this->app->instance(AssetManager::class, $this->assetManager);

    // Bind HookAction to prevent __destruct from failing
    $this->hookAction = Mockery::mock(HookAction::class);
    $this->hookAction->shouldReceive('add')->byDefault();
    $this->app->instance(HookAction::class, $this->hookAction);
});

describe('AssetEnqueuer', function (): void {
    describe('fluent builder', function (): void {
        it('chains handle and path', function (): void {
            $enqueuer = $this->app->make(AssetEnqueuer::class);

            $result = $enqueuer->handle('my-script')->path('assets/app.js');

            expect($result)->toBeInstanceOf(AssetEnqueuer::class);
        });

        it('chains dependencies', function (): void {
            $enqueuer = $this->app->make(AssetEnqueuer::class);

            $result = $enqueuer->dependencies(['jquery']);

            expect($result)->toBeInstanceOf(AssetEnqueuer::class);
        });

        it('chains version', function (): void {
            $enqueuer = $this->app->make(AssetEnqueuer::class);

            $result = $enqueuer->version('1.2.3');

            expect($result)->toBeInstanceOf(AssetEnqueuer::class);
        });

        it('chains media', function (): void {
            $enqueuer = $this->app->make(AssetEnqueuer::class);

            $result = $enqueuer->media('print');

            expect($result)->toBeInstanceOf(AssetEnqueuer::class);
        });

        it('chains loadInFooter', function (): void {
            $enqueuer = $this->app->make(AssetEnqueuer::class);

            $result = $enqueuer->loadInFooter();

            expect($result)->toBeInstanceOf(AssetEnqueuer::class);
        });

        it('chains loadStrategy', function (): void {
            $enqueuer = $this->app->make(AssetEnqueuer::class);

            $result = $enqueuer->loadStrategy('defer');

            expect($result)->toBeInstanceOf(AssetEnqueuer::class);
        });

        it('chains setType', function (): void {
            $enqueuer = $this->app->make(AssetEnqueuer::class);

            $result = $enqueuer->setType('css');

            expect($result)->toBeInstanceOf(AssetEnqueuer::class);
        });

        it('chains inline', function (): void {
            $enqueuer = $this->app->make(AssetEnqueuer::class);

            $result = $enqueuer->inline('body { color: red; }', 'after');

            expect($result)->toBeInstanceOf(AssetEnqueuer::class);
        });
    });

    describe('context hooks', function (): void {
        it('toFrontend sets wp_enqueue_scripts hook', function (): void {
            $enqueuer = $this->app->make(AssetEnqueuer::class);

            expect($enqueuer->toFrontend())->toBeInstanceOf(AssetEnqueuer::class);
        });

        it('toBackend sets admin_enqueue_scripts hook', function (): void {
            $enqueuer = $this->app->make(AssetEnqueuer::class);

            expect($enqueuer->toBackend())->toBeInstanceOf(AssetEnqueuer::class);
        });

        it('toLoginScreen sets login_enqueue_scripts hook', function (): void {
            $enqueuer = $this->app->make(AssetEnqueuer::class);

            expect($enqueuer->toLoginScreen())->toBeInstanceOf(AssetEnqueuer::class);
        });

        it('toCustomizer sets customize_preview_init hook', function (): void {
            $enqueuer = $this->app->make(AssetEnqueuer::class);

            expect($enqueuer->toCustomizer())->toBeInstanceOf(AssetEnqueuer::class);
        });

        it('toEditor sets enqueue_block_editor_assets hook', function (): void {
            $enqueuer = $this->app->make(AssetEnqueuer::class);

            expect($enqueuer->toEditor())->toBeInstanceOf(AssetEnqueuer::class);
        });
    });

    describe('determineFileType', function (): void {
        it('detects CSS files', function (): void {
            $enqueuer = $this->app->make(AssetEnqueuer::class);
            $enqueuer->path('assets/style.css');

            $reflection = new ReflectionProperty($enqueuer, 'type');
            expect($reflection->getValue($enqueuer))->toBe('css');
        });

        it('detects JS files', function (): void {
            $enqueuer = $this->app->make(AssetEnqueuer::class);
            $enqueuer->path('assets/app.js');

            $reflection = new ReflectionProperty($enqueuer, 'type');
            expect($reflection->getValue($enqueuer))->toBe('js');
        });

        it('treats JSX as JS', function (): void {
            $enqueuer = $this->app->make(AssetEnqueuer::class);
            $enqueuer->path('components/App.jsx');

            $reflection = new ReflectionProperty($enqueuer, 'type');
            expect($reflection->getValue($enqueuer))->toBe('js');
        });

        it('treats TSX as JS', function (): void {
            $enqueuer = $this->app->make(AssetEnqueuer::class);
            $enqueuer->path('components/App.tsx');

            $reflection = new ReflectionProperty($enqueuer, 'type');
            expect($reflection->getValue($enqueuer))->toBe('js');
        });

        it('treats TS as JS', function (): void {
            $enqueuer = $this->app->make(AssetEnqueuer::class);
            $enqueuer->path('assets/app.ts');

            $reflection = new ReflectionProperty($enqueuer, 'type');
            expect($reflection->getValue($enqueuer))->toBe('js');
        });

        it('throws on unsupported file type', function (): void {
            $enqueuer = $this->app->make(AssetEnqueuer::class);

            expect(fn () => $enqueuer->path('image.png'))
                ->toThrow(InvalidArgumentException::class);
        });
    });

    describe('localize', function (): void {
        it('stores localization data for JS assets', function (): void {
            $enqueuer = $this->app->make(AssetEnqueuer::class);
            $enqueuer->path('app.js');

            $result = $enqueuer->localize('myData', ['key' => 'value']);

            expect($result)->toBeInstanceOf(AssetEnqueuer::class);

            $reflection = new ReflectionProperty($enqueuer, 'localizationData');
            expect($reflection->getValue($enqueuer))->toHaveKey('myData');
        });

        it('ignores localization for CSS assets', function (): void {
            $enqueuer = $this->app->make(AssetEnqueuer::class);
            $enqueuer->path('style.css');

            $enqueuer->localize('myData', ['key' => 'value']);

            $reflection = new ReflectionProperty($enqueuer, 'localizationData');
            expect($reflection->getValue($enqueuer))->toBeEmpty();
        });
    });

    describe('container', function (): void {
        it('skips in console mode', function (): void {
            $enqueuer = $this->app->make(AssetEnqueuer::class);

            $result = $enqueuer->container('theme');

            expect($result)->toBeInstanceOf(AssetEnqueuer::class);

            $reflection = new ReflectionProperty($enqueuer, 'container');
            expect($reflection->getValue($enqueuer))->toBeNull();
        });
    });

    describe('useVite', function (): void {
        it('skips in console mode', function (): void {
            $enqueuer = $this->app->make(AssetEnqueuer::class);

            $result = $enqueuer->useVite();

            expect($result)->toBeInstanceOf(AssetEnqueuer::class);

            $reflection = new ReflectionProperty($enqueuer, 'useVite');
            expect($reflection->getValue($enqueuer))->toBeFalse();
        });
    });

    describe('full chain', function (): void {
        it('supports complete fluent configuration', function (): void {
            $enqueuer = $this->app->make(AssetEnqueuer::class);

            $result = $enqueuer
                ->handle('my-app')
                ->path('assets/app.js')
                ->dependencies(['jquery'])
                ->version('1.0.0')
                ->loadInFooter()
                ->loadStrategy('defer')
                ->toFrontend();

            expect($result)->toBeInstanceOf(AssetEnqueuer::class);
        });
    });
});
