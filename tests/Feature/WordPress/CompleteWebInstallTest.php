<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Pollora\WordPress\WordPressServiceProvider;

/**
 * What WordPress's own web installer leaves undone.
 *
 * It never runs pollora:install, so nothing migrates the Laravel tables, and
 * the rewrite rules it stores mid-install are incomplete — taxonomies and post
 * types are not all registered at that point — leaving categories, tags and
 * date archives answering 404 on a site that otherwise works.
 */
beforeEach(function (): void {
    $this->provider = (new ReflectionClass(WordPressServiceProvider::class))->newInstanceWithoutConstructor();

    // Testbench's console kernel is final, so the facade is handed a stand-in
    // rather than mocked in place.
    $this->kernel = Mockery::mock(Kernel::class);
    Artisan::swap($this->kernel);
});

describe('WordPressServiceProvider::completeWebInstall()', function (): void {
    it('migrates the database', function (): void {
        $this->kernel->shouldReceive('call')->once()->with('migrate')->andReturn(0);
        Brain\Monkey\Functions\when('delete_option')->justReturn(true);

        $this->provider->completeWebInstall();
    })->throwsNoExceptions();

    it('drops the rewrite rules so WordPress rebuilds them later', function (): void {
        $this->kernel->shouldReceive('call')->andReturn(0);

        $deleted = [];
        Brain\Monkey\Functions\when('delete_option')->alias(function (string $option) use (&$deleted): bool {
            $deleted[] = $option;

            return true;
        });

        $this->provider->completeWebInstall();

        // Flushing instead would store the same incomplete set and leave the
        // archives broken, so the option has to go rather than be rewritten.
        expect($deleted)->toBe(['rewrite_rules']);
    });
});
