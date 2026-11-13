<?php

declare(strict_types=1);

use Pollora\Plugin\Infrastructure\Services\WordPressPluginParser;

beforeEach(function (): void {
    $this->dir = sys_get_temp_dir().'/pollora-plugin-parser-'.uniqid();
    mkdir($this->dir.'/acme-shop', 0755, true);

    $this->write = function (string $contents, string $path = 'acme-shop/acme-shop.php'): string {
        $file = $this->dir.'/'.$path;
        file_put_contents($file, $contents);

        return $file;
    };

    $this->parser = new WordPressPluginParser;
});

afterEach(function (): void {
    foreach (glob($this->dir.'/*/*') ?: [] as $file) {
        unlink($file);
    }
    foreach (glob($this->dir.'/*') ?: [] as $entry) {
        is_dir($entry) ? rmdir($entry) : unlink($entry);
    }
    rmdir($this->dir);
});

function fullPluginHeader(): string
{
    return <<<'PHP'
<?php
/**
 * Plugin Name: Acme Shop
 * Plugin URI: https://example.test/acme-shop
 * Description: Sells things.
 * Version: 2.3.1
 * Author: Acme
 * Author URI: https://example.test
 * Text Domain: acme-shop
 * Domain Path: /lang
 * Requires at least: 6.4
 * Tested up to: 6.8
 * Requires PHP: 8.2
 * Network: true
 * License: GPL-2.0
 * License URI: https://example.test/license
 * Update URI: https://example.test/update
 */

defined('ABSPATH') || exit;
PHP;
}

it('reads every standard header of a plugin file', function (): void {
    $headers = $this->parser->parsePluginHeaders(($this->write)(fullPluginHeader()));

    expect($headers)->toBe([
        'Name' => 'Acme Shop',
        'PluginURI' => 'https://example.test/acme-shop',
        'Version' => '2.3.1',
        'Description' => 'Sells things.',
        'Author' => 'Acme',
        'AuthorURI' => 'https://example.test',
        'TextDomain' => 'acme-shop',
        'DomainPath' => '/lang',
        'RequiresWP' => '6.4',
        'TestedUpTo' => '6.8',
        'RequiresPHP' => '8.2',
        'Network' => true,
        'License' => 'GPL-2.0',
        'LicenseURI' => 'https://example.test/license',
        'UpdateURI' => 'https://example.test/update',
    ]);
});

it('leaves out the headers a plugin does not declare', function (): void {
    $headers = $this->parser->parsePluginHeaders(($this->write)("<?php\n/*\nPlugin Name: Bare\nVersion: 1.0\n*/\n"));

    expect($headers)->toBe(['Name' => 'Bare', 'Version' => '1.0', 'Network' => false]);
});

it('matches header names regardless of case and with comment decorations', function (): void {
    $headers = $this->parser->parsePluginHeaders(($this->write)("<?php\n// PLUGIN NAME: Shouty\n# version: 4\n"));

    expect($headers)->toBe(['Name' => 'Shouty', 'Version' => '4', 'Network' => false]);
});

it('accepts files that use CR-only line endings', function (): void {
    $headers = $this->parser->parsePluginHeaders(($this->write)("<?php\r/*\rPlugin Name: Old Mac\rVersion: 1.2\r*/"));

    expect($headers)->toBe(['Name' => 'Old Mac', 'Version' => '1.2', 'Network' => false]);
});

it('stops a header value at the end of the comment', function (): void {
    $headers = $this->parser->parsePluginHeaders(($this->write)("<?php\n/* Plugin Name: Inline */\n"));

    expect($headers['Name'])->toBe('Inline');
});

it('reads Network as false unless it is a truthy word', function (string $value, bool $expected): void {
    $headers = $this->parser->parsePluginHeaders(($this->write)("<?php\n/*\nPlugin Name: Net\nNetwork: {$value}\n*/"));

    expect($headers['Network'])->toBe($expected);
})->with([
    ['true', true],
    ['TRUE', true],
    ['yes', true],
    ['on', true],
    ['1', true],
    ['false', false],
    ['no', false],
    ['maybe', false],
]);

it('only reads the first 8 KB of the file', function (): void {
    $file = ($this->write)("<?php\n".str_repeat('// padding'.PHP_EOL, 900)."/*\nPlugin Name: Too Late\n*/");

    expect($this->parser->parsePluginHeaders($file))->not->toHaveKey('Name');
});

it('returns no headers for a missing file', function (): void {
    expect($this->parser->parsePluginHeaders($this->dir.'/nope.php'))->toBe([])
        ->and($this->parser->parsePluginHeadersWithExtra($this->dir.'/nope.php', ['Foo' => 'Foo']))->toBe([]);
});

it('reads custom headers next to the standard ones', function (): void {
    $file = ($this->write)("<?php\n/*\nPlugin Name: Custom\nRequires Plugins: woocommerce\n*/");

    $headers = $this->parser->parsePluginHeadersWithExtra($file, ['RequiresPlugins' => 'Requires Plugins']);

    expect($headers)->toBe(['Name' => 'Custom', 'Network' => false, 'RequiresPlugins' => 'woocommerce']);
});

it('lets a custom header replace a standard one of the same key', function (): void {
    $file = ($this->write)("<?php\n/*\nPlugin Name: Std\nTitle: Overridden\n*/");

    $headers = $this->parser->parsePluginHeadersWithExtra($file, ['Name' => 'Title']);

    expect($headers['Name'])->toBe('Overridden');
});

it('exposes the standard header map', function (): void {
    $defaults = $this->parser->getDefaultHeaders();

    expect($defaults['Name'])->toBe('Plugin Name')
        ->and($defaults['RequiresPHP'])->toBe('Requires PHP')
        ->and($defaults)->toHaveCount(15);
});

describe('validation', function (): void {
    it('accepts a well-formed plugin file', function (): void {
        $result = $this->parser->validatePluginFile(($this->write)(fullPluginHeader()));

        expect($result['valid'])->toBeTrue()
            ->and($result['errors'])->toBe([])
            ->and($result['headers']['Name'])->toBe('Acme Shop');
    });

    it('reports a file that does not exist', function (): void {
        expect($this->parser->validatePluginFile($this->dir.'/nope.php'))->toBe([
            'valid' => false,
            'errors' => ['Plugin main file does not exist'],
        ]);
    });

    it('reports every problem it finds', function (): void {
        $result = $this->parser->validatePluginFile(($this->write)("// not php\necho 1;\n"));

        expect($result['valid'])->toBeFalse()
            ->and($result['errors'])->toBe([
                'Plugin Name header is missing',
                'Version header is missing',
                'Plugin file must start with <?php tag',
                'Plugin should include security check to prevent direct access',
            ]);
    });

    it('accepts a direct-access guard written with ABSPATH or with defined()', function (string $guard): void {
        $result = $this->parser->validatePluginFile(($this->write)("<?php\n/*\nPlugin Name: G\nVersion: 1\n*/\n{$guard}\n"));

        expect($result['errors'])->toBe([]);
    })->with([
        "if (! defined('ABSPATH')) { exit; }",
        'if (ABSPATH) { }',
    ]);
});

describe('file detection and extraction', function (): void {
    it('recognises a file with a plugin name as a plugin', function (): void {
        expect($this->parser->hasValidPluginHeaders(($this->write)(fullPluginHeader())))->toBeTrue()
            ->and($this->parser->hasValidPluginHeaders(($this->write)("<?php\necho 1;", 'acme-shop/other.php')))->toBeFalse()
            ->and($this->parser->hasValidPluginHeaders($this->dir.'/nope.php'))->toBeFalse();
    });

    it('reads single headers, or null when absent', function (): void {
        $full = ($this->write)(fullPluginHeader());
        $bare = ($this->write)("<?php\n/*\nPlugin Name: Bare\n*/", 'acme-shop/bare.php');

        expect($this->parser->parseVersion($full))->toBe('2.3.1')
            ->and($this->parser->parseTextDomain($full))->toBe('acme-shop')
            ->and($this->parser->parseRequiredWpVersion($full))->toBe('6.4')
            ->and($this->parser->parseRequiredPhpVersion($full))->toBe('8.2')
            ->and($this->parser->parseVersion($bare))->toBeNull()
            ->and($this->parser->parseTextDomain($bare))->toBeNull()
            ->and($this->parser->parseRequiredWpVersion($bare))->toBeNull()
            ->and($this->parser->parseRequiredPhpVersion($bare))->toBeNull();
    });

    it('takes the slug from the plugin directory', function (): void {
        expect($this->parser->extractPluginSlug('/wp/plugins/acme-shop/acme-shop.php'))->toBe('acme-shop');
    });

    it('builds the basename from the directory and the file', function (): void {
        expect($this->parser->extractPluginBasename('/wp/plugins/acme-shop/main.php'))->toBe('acme-shop/main.php');
    });
});
