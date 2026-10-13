<?php

declare(strict_types=1);

namespace Tests\Unit\WordPress;

use Illuminate\Config\Repository;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Pollora\Application\Application\Services\ConsoleDetectionService;
use Pollora\Application\Infrastructure\Services\LaravelConsoleDetector;
use Pollora\Application\Infrastructure\Services\LaravelDebugDetector;
use Pollora\Hook\Domain\Contract\Action;
use Pollora\WordPress\Bootstrap;
use Pollora\WordPress\Config\ConstantManager;
use Tests\TestCase;

// Pest does not set the Composer path used by PHPUnit's isolated runner.
if (! defined('PHPUNIT_COMPOSER_INSTALL')) {
    define('PHPUNIT_COMPOSER_INSTALL', dirname(__DIR__, 3).'/vendor/autoload.php');
}

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class BootstrapEnvironmentTest extends TestCase
{
    public function test_local_environment_is_registered_before_word_press_loads(): void
    {
        putenv('WP_ENVIRONMENT_TYPE');
        $this->registerWordPressConstants();

        self::assertTrue(WP_DEBUG);
        self::assertSame('local', constant('WP_ENVIRONMENT_TYPE'));
    }

    #[DataProvider('environmentMappings')]
    public function test_application_environment_is_mapped(?string $environment, string $expected): void
    {
        putenv('WP_ENVIRONMENT_TYPE');
        $this->registerWordPressConstants($environment);

        self::assertSame($expected, constant('WP_ENVIRONMENT_TYPE'));
    }

    #[DataProvider('debugValues')]
    public function test_environment_does_not_depend_on_debug_mode(bool $debug): void
    {
        putenv('WP_ENVIRONMENT_TYPE');
        $this->registerWordPressConstants(debug: $debug);

        self::assertSame($debug, WP_DEBUG);
        self::assertSame('local', constant('WP_ENVIRONMENT_TYPE'));
    }

    #[DataProvider('nativeEnvironments')]
    public function test_native_environment_prevents_fallback_constant(string $environment): void
    {
        putenv('WP_ENVIRONMENT_TYPE='.$environment);
        $this->registerWordPressConstants();

        self::assertFalse(defined('WP_ENVIRONMENT_TYPE'));
        self::assertSame($environment, getenv('WP_ENVIRONMENT_TYPE'));
    }

    #[DataProvider('explicitValues')]
    public function test_configured_environment_is_not_normalized(mixed $value): void
    {
        putenv('WP_ENVIRONMENT_TYPE');
        $this->registerWordPressConstants(constants: ['wp_environment_type' => $value]);

        self::assertSame($value, constant('WP_ENVIRONMENT_TYPE'));
    }

    public function test_configured_constant_still_applies_with_native_environment(): void
    {
        putenv('WP_ENVIRONMENT_TYPE=production');
        $this->registerWordPressConstants(constants: ['WP_ENVIRONMENT_TYPE' => 'staging']);

        self::assertSame('staging', constant('WP_ENVIRONMENT_TYPE'));
        self::assertSame('production', getenv('WP_ENVIRONMENT_TYPE'));
    }

    #[DataProvider('explicitValues')]
    public function test_predefined_constant_is_not_overwritten(mixed $value): void
    {
        putenv('WP_ENVIRONMENT_TYPE=production');
        define('WP_ENVIRONMENT_TYPE', $value);
        $this->registerWordPressConstants(constants: ['WP_ENVIRONMENT_TYPE' => 'development']);

        self::assertSame($value, constant('WP_ENVIRONMENT_TYPE'));
    }

    /**
     * @return array<string, array{string|null, string}>
     */
    public static function environmentMappings(): array
    {
        return [
            'local' => ['local', 'local'],
            'dev' => ['dev', 'development'],
            'development' => ['development', 'development'],
            'testing' => ['testing', 'development'],
            'staging' => ['staging', 'staging'],
            'production' => ['production', 'production'],
            'prod' => ['prod', 'production'],
            'unknown' => ['preview', 'production'],
            'empty' => ['', 'production'],
            'missing' => [null, 'production'],
        ];
    }

    /**
     * @return array<string, array{bool}>
     */
    public static function debugValues(): array
    {
        return ['enabled' => [true], 'disabled' => [false]];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nativeEnvironments(): array
    {
        return [
            'local' => ['local'],
            'development' => ['development'],
            'staging' => ['staging'],
            'production' => ['production'],
            'empty' => [''],
            'invalid' => ['preview'],
        ];
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function explicitValues(): array
    {
        return [
            'valid' => ['staging'],
            'empty' => [''],
            'invalid' => ['preview'],
            'false' => [false],
        ];
    }

    /**
     * @param  array<string, mixed>  $constants
     */
    private function registerWordPressConstants(?string $environment = 'local', array $constants = [], bool $debug = true): void
    {
        $app = new Application(dirname(__DIR__, 3));
        $app->instance('config', new Repository([
            'app' => ['env' => $environment, 'debug' => $debug, 'url' => 'http://environment.test'],
            'wordpress' => ['constants' => $constants],
        ]));
        $app->instance('request', Request::create('/'));
        $app->instance('constant.manager', new ConstantManager);
        Facade::setFacadeApplication($app);

        $bootstrap = new Bootstrap(
            new ConsoleDetectionService(new LaravelConsoleDetector($app)),
            new LaravelDebugDetector($app),
            \Mockery::mock(Action::class),
        );

        $bootstrap->register();
    }
}
