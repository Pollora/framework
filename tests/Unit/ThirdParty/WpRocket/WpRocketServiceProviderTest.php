<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Pollora\ThirdParty\WpRocket\WpRocketServiceProvider;

describe('WpRocketServiceProvider', function (): void {
    beforeEach(function (): void {
        $this->originalContainer = Container::getInstance();
        $this->app = new Container;
        Container::setInstance($this->app);
        $this->registeredFilters = [];

        // Override Brain Monkey's add_filter stub to capture calls
        Brain\Monkey\Functions\when('add_filter')->alias(function ($hook, $callback): true {
            $this->registeredFilters[$hook] = $callback;

            return true;
        });
    });

    afterEach(function (): void {
        Container::setInstance($this->originalContainer);
    });

    it('registers htaccess filter as true when config enabled', function (): void {
        $this->app->instance('config', new Repository([
            'wordpress' => ['wprocket' => ['generate_htaccess' => true, 'set_cache_constant' => false]],
        ]));

        (new WpRocketServiceProvider($this->app))->boot();

        expect($this->registeredFilters)->toHaveKey('rocket_init_cache_dir_generate_htaccess');
        expect($this->registeredFilters['rocket_init_cache_dir_generate_htaccess']())->toBeTrue();
        expect($this->registeredFilters['rocket_set_wp_cache_constant']())->toBeFalse();
    });

    it('registers cache constant filter as true when config enabled', function (): void {
        $this->app->instance('config', new Repository([
            'wordpress' => ['wprocket' => ['generate_htaccess' => false, 'set_cache_constant' => true]],
        ]));

        (new WpRocketServiceProvider($this->app))->boot();

        expect($this->registeredFilters['rocket_init_cache_dir_generate_htaccess']())->toBeFalse();
        expect($this->registeredFilters['rocket_set_wp_cache_constant']())->toBeTrue();
    });

    it('defaults to false when config keys are missing', function (): void {
        $this->app->instance('config', new Repository([
            'wordpress' => ['wprocket' => []],
        ]));

        (new WpRocketServiceProvider($this->app))->boot();

        expect($this->registeredFilters['rocket_init_cache_dir_generate_htaccess']())->toBeFalse();
        expect($this->registeredFilters['rocket_set_wp_cache_constant']())->toBeFalse();
    });

    it('registers exactly two filters', function (): void {
        $this->app->instance('config', new Repository([
            'wordpress' => ['wprocket' => ['generate_htaccess' => true, 'set_cache_constant' => true]],
        ]));

        (new WpRocketServiceProvider($this->app))->boot();

        expect($this->registeredFilters)->toHaveCount(2);
        expect($this->registeredFilters)->toHaveKeys([
            'rocket_init_cache_dir_generate_htaccess',
            'rocket_set_wp_cache_constant',
        ]);
    });
});
