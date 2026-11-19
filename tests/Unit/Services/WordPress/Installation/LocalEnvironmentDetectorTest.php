<?php

declare(strict_types=1);

use Pollora\Services\WordPress\Installation\LocalEnvironmentDetector;

$localEnvironmentVariables = [
    'IS_DDEV_PROJECT', 'LARADOCK_PHP_VERSION', 'DDEV_PRIMARY_URL', 'DB_HOST', 'DB_PORT', 'PGDATABASE', 'PGUSER',
    'MYSQL_PWD', 'APP_URL', 'MYSQL_HOST', 'MYSQL_PORT', 'MYSQL_DATABASE', 'MYSQL_USER', 'MYSQL_PASSWORD',
];

beforeEach(function () use ($localEnvironmentVariables): void {
    $this->savedEnvironment = [];
    foreach ($localEnvironmentVariables as $name) {
        $this->savedEnvironment[$name] = getenv($name);
        putenv($name);
    }

    $this->savedEnvironments = (new ReflectionProperty(LocalEnvironmentDetector::class, 'environments'))->getValue();
});

afterEach(function (): void {
    foreach ($this->savedEnvironment as $name => $value) {
        putenv($value === false ? $name : $name.'='.$value);
    }

    (new ReflectionProperty(LocalEnvironmentDetector::class, 'environments'))->setValue(null, $this->savedEnvironments);
});

describe('LocalEnvironmentDetector', function (): void {
    it('falls back to the localhost defaults outside a known environment', function (): void {
        expect(LocalEnvironmentDetector::getCurrentEnvironment())->toBeNull()
            ->and(LocalEnvironmentDetector::getConfig())->toBe([
                'siteUrl' => 'http://localhost',
                'dbHost' => 'localhost',
                'dbPort' => '3306',
                'dbName' => '',
                'dbUser' => 'root',
                'dbPassword' => '',
            ]);
    });

    it('detects DDEV and takes its settings, with DDEV defaults for what is missing', function (): void {
        putenv('IS_DDEV_PROJECT=true');
        putenv('DDEV_PRIMARY_URL=https://shop.ddev.site');
        putenv('PGUSER=shopuser');

        expect(LocalEnvironmentDetector::isDdev())->toBeTrue()
            ->and(LocalEnvironmentDetector::getCurrentEnvironment())->toBe('ddev')
            ->and(LocalEnvironmentDetector::getConfig())->toBe([
                'siteUrl' => 'https://shop.ddev.site',
                'dbHost' => 'db',
                'dbPort' => '3306',
                'dbName' => 'db',
                'dbUser' => 'shopuser',
                'dbPassword' => '',
            ]);
    });

    it('does not take a project for DDEV unless the flag is exactly true', function (): void {
        putenv('IS_DDEV_PROJECT=false');

        expect(LocalEnvironmentDetector::isDdev())->toBeFalse();
    });

    it('detects Laradock from its PHP version variable', function (): void {
        putenv('LARADOCK_PHP_VERSION=8.4');
        putenv('MYSQL_DATABASE=blog');

        expect(LocalEnvironmentDetector::isLaradock())->toBeTrue()
            ->and(LocalEnvironmentDetector::getCurrentEnvironment())->toBe('laradock')
            ->and(LocalEnvironmentDetector::getConfig())->toMatchArray([
                'dbHost' => 'mysql',
                'dbName' => 'blog',
                'dbUser' => 'root',
                'dbPassword' => 'root',
            ]);
    });

    it('does not detect Laradock when the variable is empty or zero', function (): void {
        putenv('LARADOCK_PHP_VERSION=');
        expect(LocalEnvironmentDetector::isLaradock())->toBeFalse();

        putenv('LARADOCK_PHP_VERSION=0');
        expect(LocalEnvironmentDetector::isLaradock())->toBeFalse();
    });

    it('prefers DDEV when both environments look present', function (): void {
        putenv('IS_DDEV_PROJECT=true');
        putenv('LARADOCK_PHP_VERSION=8.4');

        expect(LocalEnvironmentDetector::getCurrentEnvironment())->toBe('ddev');
    });

    it('uses a registered environment once its detector says so', function (): void {
        LocalEnvironmentDetector::addEnvironment('lando', fn (): bool => true, [
            'siteUrl' => ['LANDO_URL', 'https://app.lndo.site'],
            'dbHost' => ['LANDO_DB_HOST', 'database'],
        ]);

        // DDEV and Laradock are checked first and are not detected here.
        expect(LocalEnvironmentDetector::getCurrentEnvironment())->toBe('lando')
            ->and(LocalEnvironmentDetector::getConfig())->toBe([
                'siteUrl' => 'https://app.lndo.site',
                'dbHost' => 'database',
            ]);
    });

    it('ignores a registered environment whose detector says no', function (): void {
        LocalEnvironmentDetector::addEnvironment('lando', fn (): bool => false, ['siteUrl' => ['X', 'y']]);

        expect(LocalEnvironmentDetector::getCurrentEnvironment())->toBeNull();
    });
});
