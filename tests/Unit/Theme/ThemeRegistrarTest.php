<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Pollora\Theme\Application\Services\ThemeRegistrar;
use Pollora\Theme\Domain\Contracts\ThemeModuleInterface;
use Pollora\Theme\Infrastructure\Services\WordPressThemeParser;
use Psr\Log\LoggerInterface;

function setupRegistrarMocks(Container $container, $parser): void
{
    $parser->shouldReceive('parseThemeHeaders')->andReturn(['Name' => 'Test Theme', 'Version' => '1.0.0']);
}

describe('ThemeRegistrar', function (): void {
    beforeEach(function (): void {
        $this->container = new Container;
        $this->parser = Mockery::mock(WordPressThemeParser::class)->shouldIgnoreMissing();
        $this->registrar = new ThemeRegistrar($this->container, $this->parser);
    });

    it('can register active theme', function (): void {
        $this->parser->shouldReceive('parseThemeHeaders')
            ->once()
            ->with('/path/to/theme/style.css')
            ->andReturn(['Name' => 'Test Theme', 'Version' => '1.0.0']);

        $theme = $this->registrar->register();

        expect($theme)->toBeInstanceOf(ThemeModuleInterface::class);
        expect($theme->getName())->toBe('test-theme');
        expect($theme->getPath())->toBe('/path/to/theme');
        expect($theme->isEnabled())->toBeTrue();
    });

    it('can get active theme', function (): void {
        setupRegistrarMocks($this->container, $this->parser);
        $registeredTheme = $this->registrar->register();
        $activeTheme = $this->registrar->getActiveTheme();

        expect($activeTheme)->toBe($registeredTheme);
    });

    it('returns null when no theme registered', function (): void {
        expect($this->registrar->getActiveTheme())->toBeNull();
    });

    it('can check if theme is active', function (): void {
        setupRegistrarMocks($this->container, $this->parser);

        $this->registrar->register();

        expect($this->registrar->isThemeActive('test-theme'))->toBeTrue();
        expect($this->registrar->isThemeActive('TEST-THEME'))->toBeTrue();
        expect($this->registrar->isThemeActive('other-theme'))->toBeFalse();
    });

    it('returns false when no theme registered for isActive check', function (): void {
        expect($this->registrar->isThemeActive('any-theme'))->toBeFalse();
    });

    it('can reset active theme', function (): void {
        setupRegistrarMocks($this->container, $this->parser);

        $this->registrar->register();
        $this->registrar->resetActiveTheme();

        expect($this->registrar->getActiveTheme())->toBeNull();
        expect($this->registrar->isThemeActive('test-theme'))->toBeFalse();
    });

    it('parses theme headers when no data provided', function (): void {
        $parsedData = ['Name' => 'Parsed Theme', 'Version' => '2.0.0'];

        $this->parser->shouldReceive('parseThemeHeaders')
            ->once()
            ->with('/path/to/theme/style.css')
            ->andReturn($parsedData);

        $theme = $this->registrar->register();

        expect($theme->getHeaders())->toBe($parsedData);
    });
});

describe('ThemeRegistrar discovery log', function (): void {
    /**
     * @return array{0: ThemeRegistrar, 1: ArrayObject<int, string>} A registrar and the lines it logged
     */
    function registrarLoggingInto(bool $debug): array
    {
        $lines = new ArrayObject;
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')->andReturnUsing(function (string $message) use ($lines): void {
            $lines[] = $message;
        });
        $container = new Container;
        $container->instance(LoggerInterface::class, $logger);

        $registrar = new class($container, Mockery::mock(WordPressThemeParser::class), $debug) extends ThemeRegistrar
        {
            public function __construct(Container $app, WordPressThemeParser $parser, private readonly bool $debug)
            {
                parent::__construct($app, $parser);
            }

            protected function isDebugMode(): bool
            {
                return $this->debug;
            }

            public function discovered(mixed $structure, string $type, ThemeModuleInterface $theme): void
            {
                $this->processDiscoveredStructure($structure, $type, $theme);
            }
        };

        return [$registrar, $lines];
    }

    it('logs each structure found in the theme when WP_DEBUG is on, by its fully qualified name', function (): void {
        [$registrar, $lines] = registrarLoggingInto(true);
        $theme = Mockery::mock(ThemeModuleInterface::class);
        $theme->shouldReceive('getName')->andReturn('demo');

        $registrar->discovered(new class
        {
            public function getFqn(): string
            {
                return 'Theme\\Demo\\Hooks';
            }
        }, 'hooks', $theme);
        $registrar->discovered('not an object', 'blocks', $theme);

        expect($lines->getArrayCopy())->toBe([
            'Discovered hooks in theme demo: Theme\\Demo\\Hooks',
            'Discovered blocks in theme demo: unknown',
        ]);
    });

    it('logs nothing when WP_DEBUG is off', function (): void {
        [$registrar, $lines] = registrarLoggingInto(false);
        $theme = Mockery::mock(ThemeModuleInterface::class);

        $registrar->discovered(new stdClass, 'hooks', $theme);

        expect($lines->getArrayCopy())->toBe([]);
    });
});
