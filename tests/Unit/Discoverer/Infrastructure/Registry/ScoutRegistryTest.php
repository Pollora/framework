<?php

declare(strict_types=1);

namespace Tests\Unit\Discoverer\Infrastructure\Registry;

use Illuminate\Container\Container;
use InvalidArgumentException;
use Pollora\Discoverer\Infrastructure\Registry\ScoutRegistry;
use RuntimeException;
use Spatie\StructureDiscoverer\StructureScout;
use Tests\TestCase as BaseTestCase;

/**
 * Test suite for ScoutRegistry.
 *
 * Tests the core functionality of scout registration, discovery execution,
 * validation, and error handling in the registry implementation.
 */
final class ScoutRegistryTest extends BaseTestCase
{
    private Container $container;

    private ScoutRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = new Container;
        $this->registry = new ScoutRegistry($this->container);
    }

    public function test_register_valid_scout(): void
    {
        $scoutClass = TestValidScout::class;

        $this->registry->register('test_scout', $scoutClass);

        $this->assertTrue($this->registry->has('test_scout'));
        $this->assertContains('test_scout', $this->registry->getRegistered());
    }

    public function test_register_with_initial_scouts(): void
    {
        $initialScouts = [
            'scout1' => TestValidScout::class,
            'scout2' => TestValidScout::class,
        ];

        $registry = new ScoutRegistry($this->container, $initialScouts);

        $this->assertTrue($registry->has('scout1'));
        $this->assertTrue($registry->has('scout2'));
        $this->assertEquals(['scout1', 'scout2'], $registry->getRegistered());
    }

    public function test_register_throws_exception_for_non_existent_class(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Scout class 'NonExistentClass' does not exist");

        $this->registry->register('invalid_scout', 'NonExistentClass');
    }

    public function test_register_throws_exception_for_invalid_scout_class(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must extend '.StructureScout::class);

        $this->registry->register('invalid_scout', \stdClass::class);
    }

    public function test_discover_with_valid_scout(): void
    {
        $this->container->bind(TestValidScout::class, function () {
            return new TestValidScout(['TestClass1', 'TestClass2']);
        });

        $this->registry->register('test_scout', TestValidScout::class);

        $result = $this->registry->discover('test_scout');

        $this->assertCount(2, $result);
        $this->assertEquals(['TestClass1', 'TestClass2'], $result->toArray());
    }

    public function test_discover_throws_exception_for_unknown_scout(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Scout 'unknown_scout' not found");

        $this->registry->discover('unknown_scout');
    }

    public function test_discover_throws_runtime_exception_on_discovery_failure(): void
    {
        $this->container->bind(TestValidScout::class, function () {
            return new TestFailingScout;
        });

        $this->registry->register('failing_scout', TestValidScout::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Discovery failed for scout 'failing_scout'");

        $this->registry->discover('failing_scout');
    }

    public function test_has_returns_correct_values(): void
    {
        $this->assertFalse($this->registry->has('non_existent'));

        $this->registry->register('existing', TestValidScout::class);

        $this->assertTrue($this->registry->has('existing'));
        $this->assertFalse($this->registry->has('non_existent'));
    }

    public function test_get_registered_returns_all_scout_keys(): void
    {
        $this->assertEquals([], $this->registry->getRegistered());

        $this->registry->register('scout1', TestValidScout::class);
        $this->registry->register('scout2', TestValidScout::class);

        $registered = $this->registry->getRegistered();
        $this->assertCount(2, $registered);
        $this->assertContains('scout1', $registered);
        $this->assertContains('scout2', $registered);
    }

    public function test_discover_handles_container_resolution_failure(): void
    {
        // Use an abstract class that cannot be instantiated
        $this->registry->register('test_scout', TestAbstractScout::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Discovery failed for scout 'test_scout'");

        // This will fail because the container cannot instantiate an abstract class
        $this->registry->discover('test_scout');
    }

    public function test_discover_validates_scout_instance_type(): void
    {
        $this->container->bind(TestValidScout::class, function () {
            return new \stdClass; // Wrong type
        });

        $this->registry->register('test_scout', TestValidScout::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must extend StructureScout');

        $this->registry->discover('test_scout');
    }
}

/**
 * Test scout that extends StructureScout for testing purposes.
 */
class TestValidScout extends StructureScout
{
    public function __construct(private array $results = []) {}

    protected function definition(): \Spatie\StructureDiscoverer\Discover
    {
        // This won't be called in our tests as we override get()
        return \Spatie\StructureDiscoverer\Discover::in(sys_get_temp_dir());
    }

    public function get(): array
    {
        return $this->results;
    }
}

/**
 * Test scout that throws exceptions for testing error handling.
 */
class TestFailingScout extends StructureScout
{
    protected function definition(): \Spatie\StructureDiscoverer\Discover
    {
        return \Spatie\StructureDiscoverer\Discover::in(sys_get_temp_dir());
    }

    public function get(): array
    {
        throw new \Exception('Discovery failed');
    }
}

/**
 * Abstract scout class that cannot be instantiated for testing resolution failures.
 */
abstract class TestAbstractScout extends StructureScout
{
    protected function definition(): \Spatie\StructureDiscoverer\Discover
    {
        return \Spatie\StructureDiscoverer\Discover::in(sys_get_temp_dir());
    }
}
