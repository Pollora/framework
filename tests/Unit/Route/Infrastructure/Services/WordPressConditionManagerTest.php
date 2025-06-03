<?php

declare(strict_types=1);

namespace Tests\Unit\Route\Infrastructure\Services;

use Illuminate\Container\Container;
use Pollora\Route\Infrastructure\Services\WordPressConditionManager;
use Tests\TestCase;

/**
 * @covers \Pollora\Route\Infrastructure\Services\WordPressConditionManager
 */
class WordPressConditionManagerTest extends TestCase
{
    private WordPressConditionManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new WordPressConditionManager(new Container);
    }

    public function test_loads_default_conditions(): void
    {
        $conditions = $this->manager->getConditions();

        $this->assertIsArray($conditions);
        $this->assertArrayHasKey('home', $conditions);
        $this->assertEquals('is_home', $conditions['home']);
        $this->assertArrayHasKey('single', $conditions);
        $this->assertEquals('is_single', $conditions['single']);
        $this->assertArrayHasKey('archive', $conditions);
        $this->assertEquals('is_archive', $conditions['archive']);
    }

    public function test_can_resolve_known_conditions(): void
    {
        $this->assertEquals('is_home', $this->manager->resolveCondition('home'));
        $this->assertEquals('is_single', $this->manager->resolveCondition('single'));
        $this->assertEquals('is_archive', $this->manager->resolveCondition('archive'));
    }

    public function test_returns_original_condition_for_unknown_aliases(): void
    {
        $this->assertEquals('unknown_condition', $this->manager->resolveCondition('unknown_condition'));
    }

    public function test_can_add_custom_conditions(): void
    {
        $this->manager->addCondition('custom', 'is_custom');

        $this->assertEquals('is_custom', $this->manager->resolveCondition('custom'));

        $conditions = $this->manager->getConditions();
        $this->assertArrayHasKey('custom', $conditions);
        $this->assertEquals('is_custom', $conditions['custom']);
    }
}
