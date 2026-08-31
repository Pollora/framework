<?php

declare(strict_types=1);

use Pollora\Hook\Domain\Contracts\Action;
use Pollora\Hook\Domain\Contracts\Filter;
use Pollora\Hook\Domain\Contracts\HookInterface;
use Pollora\ThirdParty\WooCommerce\Application\UseCases\RegisterWooCommerceHooksUseCase;
use Pollora\ThirdParty\WooCommerce\Domain\Contracts\ComingSoonHandlerInterface;
use Pollora\ThirdParty\WooCommerce\Domain\Contracts\TemplateResolverInterface;
use Pollora\ThirdParty\WooCommerce\Domain\Contracts\WooCommerceIntegrationInterface;

describe('RegisterWooCommerceHooksUseCase', function (): void {
    beforeEach(function (): void {
        $this->action = Mockery::mock(Action::class);
        $this->filter = Mockery::mock(Filter::class);
        $this->integration = Mockery::mock(WooCommerceIntegrationInterface::class);
        $this->templateResolver = Mockery::mock(TemplateResolverInterface::class);
        $this->comingSoonHandler = Mockery::mock(ComingSoonHandlerInterface::class);

        $this->hookReturn = Mockery::mock(HookInterface::class);

        $this->useCase = new RegisterWooCommerceHooksUseCase(
            $this->action,
            $this->filter,
            $this->integration,
            $this->templateResolver,
            $this->comingSoonHandler,
        );
    });

    it('registers plugins_loaded action on execute', function (): void {
        $this->action->shouldReceive('add')
            ->once()
            ->with('plugins_loaded', Mockery::type('Closure'));

        $this->useCase->execute();
    });

    it('registers template filters when WC_ABSPATH is defined', function (): void {
        if (! defined('WC_ABSPATH')) {
            define('WC_ABSPATH', '/var/www/html/wp-content/plugins/woocommerce/');
        }

        $capturedCallback = null;
        $this->action->shouldReceive('add')
            ->with('plugins_loaded', Mockery::on(function ($callback) use (&$capturedCallback): true {
                $capturedCallback = $callback;

                return true;
            }));

        $this->useCase->execute();

        // 7 filter.add calls: 6 template filters + template_include (coming soon)
        $this->filter->shouldReceive('add')->times(7)->andReturn($this->hookReturn);
        $this->integration->shouldReceive('loadThemeTemplateHooks')->once();
        $this->action->shouldReceive('add')
            ->with('after_setup_theme', Mockery::any());
        // Coming soon handler registers wp_loaded action
        $this->action->shouldReceive('add')
            ->with('wp_loaded', Mockery::type('Closure'));
        $this->comingSoonHandler->shouldReceive('handleTemplateInclude');

        $capturedCallback();
    });

    it('registers all expected WooCommerce filter hooks', function (): void {
        if (! defined('WC_ABSPATH')) {
            define('WC_ABSPATH', '/var/www/html/wp-content/plugins/woocommerce/');
        }

        $capturedCallback = null;
        $this->action->shouldReceive('add')
            ->with('plugins_loaded', Mockery::on(function ($callback) use (&$capturedCallback): true {
                $capturedCallback = $callback;

                return true;
            }));

        $this->useCase->execute();

        $registeredFilters = [];
        $this->filter->shouldReceive('add')
            ->andReturnUsing(function ($hook) use (&$registeredFilters) {
                $registeredFilters[] = $hook;

                return $this->hookReturn;
            });

        $this->integration->shouldReceive('loadThemeTemplateHooks');
        $this->action->shouldReceive('add')
            ->with('after_setup_theme', Mockery::any());
        // Coming soon handler registers wp_loaded action
        $this->action->shouldReceive('add')
            ->with('wp_loaded', Mockery::type('Closure'));
        $this->comingSoonHandler->shouldReceive('handleTemplateInclude');

        $capturedCallback();

        expect($registeredFilters)->toContain('woocommerce_template_loader_files');
        expect($registeredFilters)->toContain('woocommerce_locate_template');
        expect($registeredFilters)->toContain('woocommerce_locate_core_template');
        expect($registeredFilters)->toContain('wc_get_template_part');
        expect($registeredFilters)->toContain('wc_get_template');
        expect($registeredFilters)->toContain('comments_template');
        expect($registeredFilters)->toContain('template_include');
    });
});
