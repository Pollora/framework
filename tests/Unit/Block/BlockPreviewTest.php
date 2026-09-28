<?php

declare(strict_types=1);

use Pollora\Block\Infrastructure\Services\BlockPreview;

if (! class_exists('WP_REST_Request')) {
    eval('class WP_REST_Request { public function __construct(private string $method = "", private string $route = "") {} public function get_route(): string { return $this->route; } }');
}

describe('BlockPreview', function (): void {
    it('is active while the block renderer route runs, and only then', function (): void {
        $preview = new BlockPreview;
        $response = new stdClass;

        expect($preview->isActive())->toBeFalse()
            ->and($preview->start($response, null, new WP_REST_Request('POST', '/wp/v2/block-renderer/acme/card')))->toBe($response)
            ->and($preview->isActive())->toBeTrue()
            ->and($preview->end($response))->toBe($response)
            ->and($preview->isActive())->toBeFalse();
    });

    it('stays inactive for any other REST route', function (): void {
        $preview = new BlockPreview;
        $preview->start(null, null, new WP_REST_Request('GET', '/wp/v2/posts'));

        expect($preview->isActive())->toBeFalse();
    });
});
