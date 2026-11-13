<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Pollora\Support\WordPress;

use function Tests\Unit\Support\Fixtures\support_wordpress;

require_once __DIR__.'/support-fixtures.php';

beforeEach(function (): void {
    $this->wordpress = new WordPress;
    $this->previousVersion = $GLOBALS['wp_version'] ?? null;
});

afterEach(function (): void {
    if ($this->previousVersion === null) {
        unset($GLOBALS['wp_version']);
    } else {
        $GLOBALS['wp_version'] = $this->previousVersion;
    }
});

describe('WordPress::multisite()', function (): void {
    it('asks WordPress whether the installation is a multisite when no site is given', function (bool $multisite): void {
        support_wordpress(['is_multisite' => fn (): bool => $multisite]);

        expect($this->wordpress->multisite())->toBe($multisite);
    })->with([true, false]);

    it('compares the given site id with the current site', function (int $id, bool $expected): void {
        support_wordpress(['get_current_blog_id' => fn (): int => 3]);

        expect($this->wordpress->multisite($id))->toBe($expected);
    })->with([
        'current site' => [3, true],
        'other site' => [4, false],
    ]);

    it('is true when any id of the list is the current site', function (array $ids, bool $expected): void {
        support_wordpress(['get_current_blog_id' => fn (): int => 3]);

        expect($this->wordpress->multisite($ids))->toBe($expected);
    })->with([
        'contains it' => [[1, 2, 3], true],
        'does not contain it' => [[1, 2], false],
        'nested lists' => [[1, [2, [3]]], true],
        'empty list' => [[], false],
    ]);
});

describe('WordPress accessors', function (): void {
    it('reads an option with the given default', function (): void {
        support_wordpress(['get_option' => fn (string $name, mixed $default): mixed => $name === 'blogname' ? 'My blog' : $default]);

        expect($this->wordpress->option('blogname'))->toBe('My blog')
            ->and($this->wordpress->option('missing', 'fallback'))->toBe('fallback')
            ->and($this->wordpress->option('missing'))->toBeFalse();
    });

    it('returns the id of the current blog', function (): void {
        support_wordpress(['get_current_blog_id' => fn (): int => 7]);

        expect($this->wordpress->getSiteId())->toBe(7);
    });

    it('returns the current network', function (): void {
        $network = new WP_Network;
        // Called as \get_current_site(): only a global function can answer.
        Functions\when('get_current_site')->justReturn($network);

        expect($this->wordpress->site())->toBe($network);
    });

    it('returns the current user', function (): void {
        $user = new WP_User;
        support_wordpress(['wp_get_current_user' => fn (): WP_User => $user]);

        expect($this->wordpress->currentUser())->toBe($user);
    });

    it('returns the WordPress version already loaded', function (): void {
        $GLOBALS['wp_version'] = '6.8.1';

        expect($this->wordpress->version())->toBe('6.8.1');
    });
});
