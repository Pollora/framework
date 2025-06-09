<?php

declare(strict_types=1);

use Mockery\MockInterface;
use Pollora\Hook\Infrastructure\Services\Action;
use Pollora\Hook\Infrastructure\Services\Filter;
use Pollora\Plugins\WooCommerce\Application\UseCases\RegisterWooCommerceHooksUseCase;
use Pollora\Plugins\WooCommerce\Domain\Contracts\TemplateResolverInterface;
use Pollora\Plugins\WooCommerce\Domain\Contracts\WooCommerceIntegrationInterface;

describe('RegisterWooCommerceHooksUseCase', function () {
    beforeEach(function () {
        setupWordPressMocks();

        $this->action = Mockery::mock(Action::class);
        $this->filter = Mockery::mock(Filter::class);
        $this->woocommerceIntegration = Mockery::mock(WooCommerceIntegrationInterface::class);
        $this->templateResolver = Mockery::mock(TemplateResolverInterface::class);

        $this->useCase = new RegisterWooCommerceHooksUseCase(
            $this->action,
            $this->filter,
            $this->woocommerceIntegration,
            $this->templateResolver
        );
    });

    afterEach(function () {
        resetWordPressMocks();
        Mockery::close();
    });

    test('can execute and register plugins_loaded action', function () {
        $this->action->shouldReceive('add')
            ->once()
            ->with('plugins_loaded', Mockery::type('callable'));

        $this->useCase->execute();
    });

    test('registers woocommerce hooks when WC_ABSPATH is defined', function () {
        // Define WC_ABSPATH for this test
        if (!defined('WC_ABSPATH')) {
            define('WC_ABSPATH', '/plugin/woocommerce/');
        }

        // Capture the callback for plugins_loaded
        $pluginsLoadedCallback = null;
        $this->action->shouldReceive('add')
            ->once()
            ->with('plugins_loaded', Mockery::capture($pluginsLoadedCallback));

        $this->useCase->execute();

        // Now test what happens when the callback is executed
        expect($pluginsLoadedCallback)->toBeCallable();

        // Mock the hook registrations that should happen
        expectTemplateFiltersToBeRegistered($this->filter, $this->templateResolver, $this->woocommerceIntegration);
        expectSetupActionsToBeRegistered($this->woocommerceIntegration);

        // Execute the callback
        $pluginsLoadedCallback();
    });

    test('does not register hooks when WC_ABSPATH is not defined', function () {
        // Capture the callback for plugins_loaded
        $pluginsLoadedCallback = null;
        $this->action->shouldReceive('add')
            ->once()
            ->with('plugins_loaded', Mockery::capture($pluginsLoadedCallback));

        $this->useCase->execute();

        // The callback should not register any filters or actions when WC_ABSPATH is not defined
        $this->filter->shouldNotReceive('add');
        $this->action->shouldNotReceive('add')->with(Mockery::not('plugins_loaded'), Mockery::any());

        // Execute the callback (when WC_ABSPATH is not defined)
        $pluginsLoadedCallback();
    });

    test('registers template filters correctly', function () {
        if (!defined('WC_ABSPATH')) {
            define('WC_ABSPATH', '/plugin/woocommerce/');
        }

        $pluginsLoadedCallback = null;
        $this->action->shouldReceive('add')
            ->once()
            ->with('plugins_loaded', Mockery::capture($pluginsLoadedCallback));

        $this->useCase->execute();

        expectTemplateFiltersToBeRegistered($this->filter, $this->templateResolver, $this->woocommerceIntegration);
        expectSetupActionsToBeRegistered($this->woocommerceIntegration);

        $pluginsLoadedCallback();
    });

    test('registers setup actions when already in after_setup_theme', function () {
        if (!defined('WC_ABSPATH')) {
            define('WC_ABSPATH', '/plugin/woocommerce/');
        }

        setWordPressFunction('doing_action', function ($action) {
            return $action === 'after_setup_theme';
        });

        $pluginsLoadedCallback = null;
        $this->action->shouldReceive('add')
            ->once()
            ->with('plugins_loaded', Mockery::capture($pluginsLoadedCallback));

        $this->useCase->execute();

        // Expect template filters to be registered
        expectTemplateFiltersToBeRegistered($this->filter, $this->templateResolver, $this->woocommerceIntegration);

        // Load theme template hooks should be called
        $this->woocommerceIntegration->shouldReceive('loadThemeTemplateHooks')
            ->once();

        // Theme support should be added immediately
        $this->woocommerceIntegration->shouldReceive('addThemeSupport')
            ->once();

        // No after_setup_theme action should be registered since we're already in it
        $this->action->shouldNotReceive('add')
            ->with('after_setup_theme', Mockery::any());

        $pluginsLoadedCallback();
    });

    test('registers setup actions when not in after_setup_theme', function () {
        if (!defined('WC_ABSPATH')) {
            define('WC_ABSPATH', '/plugin/woocommerce/');
        }

        setWordPressFunction('doing_action', function ($action) {
            return false; // Not in after_setup_theme
        });

        $pluginsLoadedCallback = null;
        $this->action->shouldReceive('add')
            ->once()
            ->with('plugins_loaded', Mockery::capture($pluginsLoadedCallback));

        $this->useCase->execute();

        // Expect template filters to be registered
        expectTemplateFiltersToBeRegistered($this->filter, $this->templateResolver, $this->woocommerceIntegration);

        // Load theme template hooks should be called
        $this->woocommerceIntegration->shouldReceive('loadThemeTemplateHooks')
            ->once();

        // after_setup_theme action should be registered
        $this->action->shouldReceive('add')
            ->once()
            ->with('after_setup_theme', [$this->woocommerceIntegration, 'addThemeSupport']);

        $pluginsLoadedCallback();
    });

});

/**
 * Helper method to set up expectations for template filter registration
 */
function expectTemplateFiltersToBeRegistered($filter, $templateResolver, $woocommerceIntegration): void
{
    $filter->shouldReceive('add')
        ->once()
        ->with('woocommerce_template_loader_files', [$templateResolver, 'extendTemplateLoaderFiles'], 10, 2);

    $filter->shouldReceive('add')
        ->once()
        ->with('woocommerce_locate_template', [$woocommerceIntegration, 'template'], 10, 2);

    $filter->shouldReceive('add')
        ->once()
        ->with('woocommerce_locate_core_template', [$woocommerceIntegration, 'template'], 10, 2);

    $filter->shouldReceive('add')
        ->once()
        ->with('wc_get_template_part', [$woocommerceIntegration, 'template']);

    $filter->shouldReceive('add')
        ->once()
        ->with('wc_get_template', [$woocommerceIntegration, 'template'], 1000);

    $filter->shouldReceive('add')
        ->once()
        ->with('comments_template', [$woocommerceIntegration, 'reviewsTemplate'], 11);
}

/**
 * Helper method to set up expectations for setup action registration
 */
function expectSetupActionsToBeRegistered($woocommerceIntegration): void
{
    $woocommerceIntegration->shouldReceive('loadThemeTemplateHooks')
        ->once();
}