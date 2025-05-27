<?php

declare(strict_types=1);

namespace Tests\Unit\Route\Infrastructure;

use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Pollora\Route\Application\Services\BuildTemplateHierarchyService;
use Pollora\Route\Domain\Contracts\ConditionResolverInterface;
use Pollora\Route\Domain\Contracts\TemplateResolverInterface;
use Pollora\Route\Domain\Services\TemplatePriorityComparator;
use Pollora\Route\Domain\Services\WordPressContextBuilder;
use Pollora\Route\Infrastructure\Laravel\ExtendedRoute;
use Pollora\Route\Infrastructure\WordPress\ConditionalTagsResolver;
use Tests\TestCase;

/**
 * Test template priority checking functionality in ExtendedRoute
 */
class ExtendedRouteTemplatePriorityTest extends TestCase
{
    private Container $container;

    private ExtendedRoute $route;

    private ConditionResolverInterface $conditionResolver;

    private TemplatePriorityComparator $templateComparator;

    private BuildTemplateHierarchyService $hierarchyService;

    protected function setUp(): void
    {
        parent::setUp();

        setupWordPressMocks();

        $this->container = new Container;

        // Create services
        $this->conditionResolver = new ConditionalTagsResolver([
            'conditions' => [
                'is_home' => 'home',
                'is_page' => 'page',
                'is_single' => 'single',
            ],
        ]);

        $contextBuilder = new WordPressContextBuilder;
        $templateResolver = \Mockery::mock(TemplateResolverInterface::class);
        $templateResolver->shouldReceive('findTemplate')->andReturn(null)->byDefault();
        $templateResolver->shouldReceive('templateExists')->andReturn(false)->byDefault();

        $this->templateComparator = new TemplatePriorityComparator($templateResolver, [
            'template_existence_bonus' => 200,
            'route_parameter_weight' => 25,
            'template_depth_weight' => 50,
            'template_specificity_multiplier' => 2,
            'route_condition_weight' => 0.5,
            'same_specificity_prefers_template' => true,
            'laravel_route_override_threshold' => 1500,
            'debug_comparison' => false,
        ]);

        $this->hierarchyService = new BuildTemplateHierarchyService(
            $templateResolver,
            $contextBuilder,
            []
        );

        // Create route with services injected
        $this->route = new ExtendedRoute(['GET'], '/test', function () {
            return 'test response';
        });

        // Create a mock router
        $mockRouter = \Mockery::mock(\Illuminate\Routing\Router::class);
        $this->route->setRouter($mockRouter);
        $this->route->setContainer($this->container);

        // Inject template priority services
        $this->route->setConditionResolver($this->conditionResolver);
        $this->route->setTemplatePriorityComparator($this->templateComparator);
        $this->route->setTemplateHierarchyService($this->hierarchyService);
    }

    /** @test */
    public function route_services_are_properly_injected(): void
    {
        $reflection = new \ReflectionClass($this->route);

        $templateComparatorProp = $reflection->getProperty('templateComparator');
        $templateComparatorProp->setAccessible(true);
        $templateComparatorValue = $templateComparatorProp->getValue($this->route);

        $hierarchyServiceProp = $reflection->getProperty('hierarchyService');
        $hierarchyServiceProp->setAccessible(true);
        $hierarchyServiceValue = $hierarchyServiceProp->getValue($this->route);

        $conditionResolverProp = $reflection->getProperty('conditionResolver');
        $conditionResolverProp->setAccessible(true);
        $conditionResolverValue = $conditionResolverProp->getValue($this->route);

        $this->assertInstanceOf(TemplatePriorityComparator::class, $templateComparatorValue);
        $this->assertInstanceOf(BuildTemplateHierarchyService::class, $hierarchyServiceValue);
        $this->assertInstanceOf(ConditionResolverInterface::class, $conditionResolverValue);
    }

    /** @test */
    public function route_matches_when_wordpress_condition_is_true_and_template_does_not_override(): void
    {
        // Set up WordPress condition to return true
        setWordPressConditions([
            'is_home' => true,
        ]);

        // Set route as WordPress route with condition
        $this->route->setIsWordPressRoute();
        $this->route->setWordPressCondition('is_home');

        // Create a real template comparator but mock its dependencies for controlled behavior
        $mockTemplateResolver = \Mockery::mock(TemplateResolverInterface::class);
        $mockTemplateResolver->shouldReceive('findTemplate')->andReturn(null);

        $testComparator = new TemplatePriorityComparator($mockTemplateResolver, [
            'template_existence_bonus' => 200,
            'laravel_route_override_threshold' => 1500,
        ]);

        $this->route->setTemplatePriorityComparator($testComparator);

        $request = Request::create('/test', 'GET');

        $this->assertTrue($this->route->matches($request));
    }

    /** @test */
    public function template_priority_system_integration_works(): void
    {
        // Set up WordPress condition to return true
        setWordPressConditions([
            'is_home' => true,
        ]);

        // Set route as WordPress route with condition
        $this->route->setIsWordPressRoute();
        $this->route->setWordPressCondition('is_home');

        // Use the real template comparator with mocked resolver
        $mockTemplateResolver = \Mockery::mock(TemplateResolverInterface::class);
        $mockTemplateResolver->shouldReceive('findTemplate')->andReturn(null);
        $mockTemplateResolver->shouldReceive('templateExists')->andReturn(false);

        $testComparator = new TemplatePriorityComparator($mockTemplateResolver, [
            'template_existence_bonus' => 200,
            'laravel_route_override_threshold' => 1500,
        ]);

        $this->route->setTemplatePriorityComparator($testComparator);

        $request = Request::create('/test', 'GET');

        // Test that the system works end-to-end
        $result = $this->route->matches($request);

        // Verify that the route matches (because no template exists to override it)
        $this->assertTrue($result);
        
        // Verify that the WordPress condition is properly set
        $this->assertEquals('is_home', $this->route->getWordPressCondition());
        $this->assertTrue($this->route->isWordPressRoute());
    }

    /** @test */
    public function route_does_not_match_when_wordpress_condition_is_false(): void
    {
        // Set up WordPress condition to return false
        setWordPressConditions([
            'is_home' => false,
        ]);

        // Set route as WordPress route with condition
        $this->route->setIsWordPressRoute();
        $this->route->setWordPressCondition('is_home');

        $request = Request::create('/test', 'GET');

        $this->assertFalse($this->route->matches($request));
    }

    /** @test */
    public function route_matches_regular_laravel_routes_normally(): void
    {
        // Do not set as WordPress route - should behave like normal Laravel route
        $request = Request::create('/test', 'GET');

        // Mock the compiled route for Laravel route matching
        $compiledRoute = \Mockery::mock(\Symfony\Component\Routing\CompiledRoute::class);
        $compiledRoute->shouldReceive('getRegex')->andReturn('#^/test$#s');
        $compiledRoute->shouldReceive('getVariables')->andReturn([]);
        $compiledRoute->shouldReceive('getHostRegex')->andReturn(null);
        $compiledRoute->shouldReceive('getScheme')->andReturn('');
        $compiledRoute->shouldReceive('getMethods')->andReturn(['GET']);

        $reflection = new \ReflectionClass($this->route);
        $compiledProp = $reflection->getProperty('compiled');
        $compiledProp->setAccessible(true);
        $compiledProp->setValue($this->route, $compiledRoute);

        $this->assertTrue($this->route->matches($request));
    }

    /** @test */
    public function route_handles_missing_services_gracefully(): void
    {
        // Create route without services
        $routeWithoutServices = new ExtendedRoute(['GET'], '/test', function () {
            return 'test';
        });

        // Set up WordPress condition to return true
        setWordPressConditions([
            'is_home' => true,
        ]);

        // Set as WordPress route but without services
        $routeWithoutServices->setIsWordPressRoute();
        $routeWithoutServices->setWordPressCondition('is_home');
        $routeWithoutServices->setConditionResolver($this->conditionResolver);

        $request = Request::create('/test', 'GET');

        // Should still match because template priority check is skipped when services are missing
        $this->assertTrue($routeWithoutServices->matches($request));
    }

    /** @test */
    public function evaluate_wordpress_condition_handles_errors_gracefully(): void
    {
        // Set up WordPress condition to return true
        setWordPressConditions([
            'is_home' => true,
        ]);

        // Set route as WordPress route with condition
        $this->route->setIsWordPressRoute();
        $this->route->setWordPressCondition('is_home');

        // Create a template comparator with a mocked resolver that throws an exception
        $mockTemplateResolver = \Mockery::mock(TemplateResolverInterface::class);
        $mockTemplateResolver->shouldReceive('findTemplate')->andThrow(new \Exception('Template comparison error'));

        $testComparator = new TemplatePriorityComparator($mockTemplateResolver, [
            'template_existence_bonus' => 200,
            'laravel_route_override_threshold' => 1500,
        ]);

        $this->route->setTemplatePriorityComparator($testComparator);

        $request = Request::create('/test', 'GET');

        // Should still match because errors are caught and route is allowed to proceed
        $this->assertTrue($this->route->matches($request));
    }

    protected function tearDown(): void
    {
        resetWordPressMocks();
        parent::tearDown();
    }
}
