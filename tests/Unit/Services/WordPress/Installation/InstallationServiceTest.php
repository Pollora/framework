<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Pollora\Services\WordPress\Installation\DTO\InstallationConfig;
use Pollora\Services\WordPress\Installation\InstallationService;
use Pollora\Services\WordPress\Installation\WordPressInstallationException;
use Pollora\Services\WordPress\Installation\WordPressInstallLoaderService;

function covInstallationConfig(bool $isPublic = true): InstallationConfig
{
    return new InstallationConfig('Acme', 'Just another site', 'admin', 'admin@acme.test', 'secret', 'fr_FR', $isPublic);
}

/**
 * Run install() with the WordPress functions it calls recorded.
 *
 * @param  mixed  $installResult  What wp_install() returns
 * @param  array<string, mixed>  $uploadDir  What wp_upload_dir() returns
 * @return array{options: array<string, mixed>, install: list<mixed>, deleted: list<string>, filtered: list<string>}
 */
function covRunInstall(mixed $installResult, array $uploadDir = ['error' => false], bool $isPublic = true): array
{
    $record = ['options' => [], 'install' => [], 'deleted' => [], 'filtered' => []];

    $loader = Mockery::mock(WordPressInstallLoaderService::class);
    $loader->shouldReceive('bootstrap')->once();

    Functions\when('wp_install')->alias(function (...$args) use (&$record, $installResult) {
        $record['install'] = $args;

        return $installResult;
    });
    Functions\when('is_wp_error')->alias(fn ($thing): bool => $thing instanceof CovWpErrorDouble);
    Functions\when('update_option')->alias(function (string $name, $value) use (&$record): bool {
        $record['options'][$name] = $value;

        return true;
    });
    Functions\when('delete_option')->alias(function (string $name) use (&$record): bool {
        $record['deleted'][] = $name;

        return true;
    });
    Functions\when('remove_all_filters')->alias(function (string $name) use (&$record): bool {
        $record['filtered'][] = $name;

        return true;
    });
    Functions\when('wp_upload_dir')->justReturn($uploadDir);

    $rewrite = new class
    {
        public int $inits = 0;

        public function init(): void
        {
            $this->inits++;
        }
    };
    $GLOBALS['wp_rewrite'] = $rewrite;

    try {
        (new InstallationService($loader))->install(covInstallationConfig($isPublic));
    } finally {
        $record['rewriteInits'] = $rewrite->inits;
        unset($GLOBALS['wp_rewrite']);
    }

    return $record;
}

class CovWpErrorDouble
{
    public function get_error_message(): string
    {
        return 'Database is gone';
    }
}

describe('InstallationService::install', function (): void {
    it('installs WordPress with the configured site and admin account', function (): void {
        $record = covRunInstall(['user_id' => 1]);

        expect($record['install'])->toBe(['Acme', 'admin', 'admin@acme.test', true, '', 'secret', 'fr_FR']);
    });

    it('turns on pretty permalinks and drops the rewrite rules so they are rebuilt later', function (): void {
        $record = covRunInstall(['user_id' => 1]);

        expect($record['options']['permalink_structure'])->toBe('/%postname%/')
            ->and($record['filtered'])->toBe(['pre_option_permalink_structure'])
            ->and($record['rewriteInits'])->toBe(1)
            ->and($record['deleted'])->toBe(['rewrite_rules']);
    });

    it('stores the description and the search engine visibility', function (): void {
        expect(covRunInstall(['user_id' => 1])['options'])->toMatchArray([
            'blogdescription' => 'Just another site',
            'blog_public' => '1',
        ]);

        expect(covRunInstall(['user_id' => 1], isPublic: false)['options']['blog_public'])->toBe('0');
    });

    it('throws the WordPress error message when the installation fails', function (): void {
        expect(fn (): array => covRunInstall(new CovWpErrorDouble))
            ->toThrow(WordPressInstallationException::class, 'Database is gone');
    });

    it('throws when the upload directory cannot be prepared', function (): void {
        expect(fn (): array => covRunInstall(['user_id' => 1], ['error' => 'Unable to create directory wp-content/uploads']))
            ->toThrow(WordPressInstallationException::class, 'Unable to create directory wp-content/uploads');
    });
});
