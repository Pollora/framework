<?php

declare(strict_types=1);

use Pollora\WordPress\Multisite;

describe('Multisite declaration', function (): void {
    it('is not configured on a single site', function (): void {
        config()->set('wordpress.constants', ['wp_allow_multisite' => true]);

        expect(Multisite::isConfigured())->toBeFalse();
    });

    it('reads the MULTISITE constant out of the configuration, whatever its case', function (mixed $value, bool $expected): void {
        config()->set('wordpress.constants', ['multisite' => $value]);

        expect(Multisite::isConfigured())->toBe($expected);
    })->with([
        'on' => [true, true],
        'off' => [false, false],
        'truthy string' => ['1', true],
    ]);

    it('reads an upper-case key too', function (): void {
        config()->set('wordpress.constants', ['OTHER' => true, 'MULTISITE' => true]);

        expect(Multisite::isConfigured())->toBeTrue();
    });
});

describe('Multisite main site home', function (): void {
    it('drops the WordPress directory the installer stored', function (string $stored): void {
        expect(Multisite::mainSiteHome($stored, 'https://example.test'))->toBe('https://example.test');
    })->with([
        'plain' => ['https://example.test/cms'],
        'trailing slash' => ['https://example.test/cms/'],
    ]);

    it('tolerates a trailing slash on the application URL', function (): void {
        expect(Multisite::mainSiteHome('https://example.test/cms', 'https://example.test/'))->toBe('https://example.test');
    });

    it('keeps a home someone chose', function (string $stored): void {
        expect(Multisite::mainSiteHome($stored, 'https://example.test'))->toBe($stored);
    })->with([
        'another domain' => ['https://other.test/cms'],
        'the application URL itself' => ['https://example.test'],
        'a deeper path' => ['https://example.test/cms/blog'],
    ]);

    it('leaves what is not a URL string alone', function (): void {
        expect(Multisite::mainSiteHome(false, 'https://example.test'))->toBeFalse()
            ->and(Multisite::mainSiteHome('https://example.test/cms', ''))->toBe('https://example.test/cms');
    });
});

describe('Multisite main site detection', function (): void {
    it("is the main site when the blog is the network's blog", function (): void {
        expect(Multisite::isMainSite(['current_site' => (object) ['blog_id' => '1'], 'blog_id' => 1]))->toBeTrue();
    });

    it('is not when another blog is served', function (): void {
        expect(Multisite::isMainSite(['current_site' => (object) ['blog_id' => 1], 'blog_id' => 2]))->toBeFalse();
    });

    it('is not before WordPress knows the network', function (array $globals): void {
        expect(Multisite::isMainSite($globals))->toBeFalse();
    })->with([
        'nothing' => [[]],
        'no network' => [['blog_id' => 1]],
        'no blog' => [['current_site' => (object) ['blog_id' => 1]]],
        'network without blog id' => [['current_site' => (object) [], 'blog_id' => 1]],
        'not an object' => [['current_site' => 'x', 'blog_id' => 1]],
    ]);
});

describe('Multisite console host', function (): void {
    it('keeps the port of the application URL', function (): void {
        expect(Multisite::consoleHost('http://multi.test:8081/site'))->toBe('multi.test:8081');
    });

    it('is the bare host without a port', function (): void {
        expect(Multisite::consoleHost('https://multi.test'))->toBe('multi.test');
    });

    it('reads a URL without scheme as http', function (): void {
        expect(Multisite::consoleHost('multi.test:8081'))->toBe('multi.test:8081');
    });

    it('falls back to localhost without a host', function (): void {
        expect(Multisite::consoleHost(''))->toBe('localhost');
    });

    it('presents the path of the application as the request, or the root', function (string $url, string $expected): void {
        expect(Multisite::consoleRequestUri($url))->toBe($expected);
    })->with([
        'root' => ['http://multi.test', '/'],
        'slash' => ['http://multi.test/', '/'],
        'directory' => ['http://multi.test/network', '/network/'],
        'padded directory' => ['http://multi.test//network//', '/network/'],
        'empty' => ['', '/'],
    ]);
});

describe('Multisite silent redirect', function (): void {
    it('recognises the statuses a redirect leaves', function (int|false $status, bool $expected): void {
        expect(Multisite::isRedirectStatus($status))->toBe($expected);
    })->with([
        [301, true], [302, true], [303, true], [307, true], [308, true],
        [200, false], [304, false], [404, false], [500, false], [false, false],
    ]);

    it('names the host and the network domain', function (): void {
        $message = Multisite::redirectMessage('multi.test', 'multi.test:8081');

        expect($message)->toContain('"multi.test"')
            ->and($message)->toContain('"multi.test:8081"')
            ->and($message)->toContain('DOMAIN_CURRENT_SITE')
            ->and($message)->toEndWith("\n");
    });

    it('does not invent a network domain', function (): void {
        expect(Multisite::redirectMessage('multi.test', null))->toContain('is not the one of the network)');
    });
});
