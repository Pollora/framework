<?php

declare(strict_types=1);

use Pollora\Plugin\Domain\Exceptions\PluginException;
use Pollora\Proxy\Exceptions\UnsupportedDatabaseCallException;
use Pollora\Services\WordPress\Installation\Exceptions\LanguageApiException;
use Pollora\Theme\Domain\Exceptions\ThemeSetupException;
use Pollora\WordPress\Exceptions\WordPressNotLoadedException;

it('keeps the theme setup errors catchable as a RuntimeException, each saying what to call', function (): void {
    expect(ThemeSetupException::serviceNotRegistered())->toBeInstanceOf(RuntimeException::class)
        ->and(ThemeSetupException::serviceNotRegistered()->getMessage())->toStartWith('Unable to resolve ThemeService.')
        ->and(ThemeSetupException::nameRequired()->getMessage())->toStartWith('Theme name is required for self-registered themes.')
        ->and(ThemeSetupException::collectionFactoryMissing()->getMessage())->toContain('ThemeCollection::setFactory()')
        ->and(ThemeSetupException::configRepositoryMissing()->getMessage())->toContain('ThemeConfig::setRepository()')
        ->and(ThemeSetupException::stubNotBundled('page')->getMessage())->toStartWith('The stub template "page" is not bundled with the framework.');
});

it('chains the reason a theme component failed to register', function (): void {
    $previous = new LogicException('boom');
    $exception = ThemeSetupException::componentFailed('App\Component', $previous);

    expect($exception->getMessage())->toBe('Failed to register component App\Component: boom')
        ->and($exception->getPrevious())->toBe($previous);
});

it('names the plugin that was not found, and where to list them', function (): void {
    expect(PluginException::notFound('demo')->getMessage())->toStartWith("Plugin 'demo' not found.")
        ->toContain('pollora:plugin:list');
});

it('refuses the legacy mysql_connect call with the supported one named', function (): void {
    expect(UnsupportedDatabaseCallException::mysqlConnect())->toBeInstanceOf(RuntimeException::class)
        ->and(UnsupportedDatabaseCallException::mysqlConnect()->getMessage())->toContain('mysqli_real_connect');
});

it('tells the two ways the languages API fails apart', function (): void {
    expect(LanguageApiException::unreachable()->getMessage())->toStartWith('Failed to fetch languages from WordPress API')
        ->and(LanguageApiException::invalidResponse()->getMessage())->toStartWith('Invalid JSON response from WordPress API')
        ->and(LanguageApiException::invalidResponse())->toBeInstanceOf(RuntimeException::class);
});

it('says WordPress has to be loaded before a query runs', function (): void {
    expect(WordPressNotLoadedException::coreFunctionsMissing())->toBeInstanceOf(RuntimeException::class)
        ->and(WordPressNotLoadedException::coreFunctionsMissing()->getMessage())->toStartWith('The WordPress core functions are not available.');
});
