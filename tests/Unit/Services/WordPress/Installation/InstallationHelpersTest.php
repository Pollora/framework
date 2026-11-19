<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Pollora\Hook\Domain\Contract\Filter;
use Pollora\Services\WordPress\Installation\Exceptions\LanguageApiException;
use Pollora\Services\WordPress\Installation\LanguageService;
use Pollora\Services\WordPress\Installation\WordPressInstallLoaderService;

describe('WordPressInstallLoaderService cookie path', function (): void {
    it('derives the cookie path from the path of the application URL', function (string $url, string $expected): void {
        $app = Mockery::mock(Application::class);
        $app->shouldReceive('get')->with(Filter::class)->andReturn(Mockery::mock(Filter::class));

        $service = new WordPressInstallLoaderService($app);
        $method = new ReflectionMethod($service, 'getCookiePath');

        expect($method->invoke($service, $url))->toBe($expected);
    })->with([
        'site at the domain root' => ['https://shop.test', '/'],
        'site in a subdirectory' => ['http://localhost/blog', '/blog/'],
        'with a port' => ['http://localhost:8080/shop', '/shop/'],
    ]);
});

describe('LanguageService translations API response', function (): void {
    $parse = function (string $json): array {
        $service = new LanguageService;

        return (new ReflectionMethod($service, 'parseLanguagesResponse'))->invoke($service, $json);
    };

    it('lists English first, then every translation as "English name - native name"', function () use ($parse): void {
        $languages = $parse(json_encode(['translations' => [
            ['language' => 'fr_FR', 'english_name' => 'French (France)', 'native_name' => 'Français'],
            ['language' => 'de_DE', 'english_name' => 'German', 'native_name' => 'Deutsch'],
        ]]));

        expect($languages)->toBe([
            'en_US' => 'English (United States)',
            'fr_FR' => 'French (France) - Français',
            'de_DE' => 'German - Deutsch',
        ]);
    });

    it('falls back to the built-in languages when the response has no translations', function () use ($parse): void {
        $languages = $parse('{"other": []}');

        expect($languages)->toHaveKeys(['en_US', 'fr_FR', 'de_DE'])
            ->and($languages['fr_FR'])->toBe('French (France)');
    });

    it('rejects a response that is not JSON', function () use ($parse): void {
        expect(fn (): array => $parse('<html>502</html>'))->toThrow(LanguageApiException::class, 'Invalid JSON response');
    });
});
