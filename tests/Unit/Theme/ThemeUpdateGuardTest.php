<?php

declare(strict_types=1);

use Pollora\Theme\Infrastructure\Services\ThemeUpdateGuard;

beforeEach(function (): void {
    $this->themesPath = sys_get_temp_dir().'/pollora-themes-'.uniqid();
    mkdir($this->themesPath.'/scaffolded', 0755, true);

    $this->guard = new ThemeUpdateGuard($this->themesPath);
});

afterEach(function (): void {
    @rmdir($this->themesPath.'/scaffolded');
    @rmdir($this->themesPath);
});

function updateTransient(array $response, array $noUpdate = []): stdClass
{
    $transient = new stdClass;
    $transient->response = $response;
    $transient->no_update = $noUpdate;

    return $transient;
}

describe('ThemeUpdateGuard', function (): void {
    it('drops an update aimed at a theme of the project', function (): void {
        // wordpress.org answers for any theme whose directory name matches one
        // of its own slugs — "default", which is what pollora:install generates.
        $transient = updateTransient(['scaffolded' => ['new_version' => '1.7.2']]);

        $filtered = $this->guard->filterUpdates($transient);

        expect($filtered->response)->toBe([])
            ->and($filtered->no_update)->toHaveKey('scaffolded');
    });

    it('leaves updates for themes it did not scaffold', function (): void {
        $transient = updateTransient(['twentytwentyfive' => ['new_version' => '1.2']]);

        expect($this->guard->filterUpdates($transient)->response)->toHaveKey('twentytwentyfive');
    });

    it('filters only the project themes out of a mixed answer', function (): void {
        $transient = updateTransient([
            'scaffolded' => ['new_version' => '1.7.2'],
            'twentytwentyfive' => ['new_version' => '1.2'],
        ]);

        $filtered = $this->guard->filterUpdates($transient);

        expect(array_keys($filtered->response))->toBe(['twentytwentyfive'])
            ->and(array_keys($filtered->no_update))->toBe(['scaffolded']);
    });

    it('refuses a stylesheet that climbs out of the themes directory', function (): void {
        $transient = updateTransient(['../../etc' => ['new_version' => '1.0']]);

        expect($this->guard->filterUpdates($transient)->response)->toHaveKey('../../etc');
    });

    it('passes anything that is not an update transient straight through', function (): void {
        expect($this->guard->filterUpdates(false))->toBeFalse()
            ->and($this->guard->filterUpdates(null))->toBeNull();

        $empty = new stdClass;
        expect($this->guard->filterUpdates($empty))->toBe($empty);
    });
});
