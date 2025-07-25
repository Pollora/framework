<?php

declare(strict_types=1);

namespace Tests\Feature\Option;

use PHPUnit\Framework\TestCase;
use Pollora\Support\Facades\Option;

final class OptionIntegrationTest extends TestCase
{
    public function test_facade_class_exists(): void
    {
        $this->assertTrue(class_exists(Option::class));
    }

    public function test_facade_has_correct_accessor(): void
    {
        $reflection = new \ReflectionClass(Option::class);
        $method = $reflection->getMethod('getFacadeAccessor');
        $method->setAccessible(true);

        $accessor = $method->invoke(null);

        $this->assertEquals(\Pollora\Option\Application\Services\OptionService::class, $accessor);
    }

    public function test_facade_has_forget_alias(): void
    {
        $this->assertTrue(method_exists(Option::class, 'forget'));
    }
}
