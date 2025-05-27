<?php

declare(strict_types=1);

namespace Tests\Unit\Route\Domain\Models;

use PHPUnit\Framework\TestCase;
use Pollora\Route\Domain\Contracts\ConditionResolverInterface;
use Pollora\Route\Domain\Exceptions\InvalidRouteConditionException;
use Pollora\Route\Domain\Models\RouteCondition;
use Pollora\Route\Infrastructure\WordPress\ConditionalTagsResolver;

/**
 * @covers \Pollora\Route\Domain\Models\RouteCondition
 */
class RouteConditionTest extends TestCase
{
    public function testFromWordPressTag(): void
    {
        $condition = RouteCondition::fromWordPressTag('is_page', ['about']);

        $this->assertEquals('wordpress', $condition->getType());
        $this->assertEquals('is_page', $condition->getCondition());
        $this->assertEquals(['about'], $condition->getParameters());
    }

    public function testFromLaravel(): void
    {
        $condition = RouteCondition::fromLaravel('users/{id}');

        $this->assertEquals('laravel', $condition->getType());
        $this->assertEquals('users/{id}', $condition->getCondition());
        $this->assertEmpty($condition->getParameters());
    }

    public function testFromCustom(): void
    {
        $condition = RouteCondition::fromCustom('is_premium', ['level' => 'gold']);

        $this->assertEquals('custom', $condition->getType());
        $this->assertEquals('is_premium', $condition->getCondition());
        $this->assertEquals(['level' => 'gold'], $condition->getParameters());
    }

    public function testSpecificityForWordPressConditions(): void
    {
        $pageCondition = RouteCondition::fromWordPressTag('is_page');
        $singleCondition = RouteCondition::fromWordPressTag('is_single');
        $categoryCondition = RouteCondition::fromWordPressTag('is_category');
        $homeCondition = RouteCondition::fromWordPressTag('is_home');

        $this->assertGreaterThan($singleCondition->getSpecificity(), $pageCondition->getSpecificity());
        $this->assertGreaterThan($categoryCondition->getSpecificity(), $singleCondition->getSpecificity());
        $this->assertGreaterThan($homeCondition->getSpecificity(), $categoryCondition->getSpecificity());
    }

    public function testSpecificityWithParameters(): void
    {
        $withParams = RouteCondition::fromWordPressTag('is_page', ['about']);
        $withoutParams = RouteCondition::fromWordPressTag('is_page');

        $this->assertGreaterThan($withoutParams->getSpecificity(), $withParams->getSpecificity());
    }

    public function testSpecificityComparison(): void
    {
        $custom = RouteCondition::fromCustom('is_vip');
        $wordpress = RouteCondition::fromWordPressTag('is_page');
        $laravel = RouteCondition::fromLaravel('/users/{id}');

        $this->assertTrue($custom->isMoreSpecificThan($wordpress));
        $this->assertTrue($wordpress->isMoreSpecificThan($laravel));
        $this->assertTrue($custom->isMoreSpecificThan($laravel));
    }

    public function testUniqueIdentifierWithoutParameters(): void
    {
        $condition = RouteCondition::fromWordPressTag('is_page');
        $this->assertEquals('is_page', $condition->toUniqueIdentifier());
    }

    public function testUniqueIdentifierWithParameters(): void
    {
        $condition = RouteCondition::fromWordPressTag('is_page', ['about']);
        $expected = 'is_page_' . md5(serialize(['about']));
        $this->assertEquals($expected, $condition->toUniqueIdentifier());
    }

    public function testHasParameters(): void
    {
        $withParams = RouteCondition::fromWordPressTag('is_page', ['about']);
        $withoutParams = RouteCondition::fromWordPressTag('is_page');

        $this->assertTrue($withParams->hasParameters());
        $this->assertFalse($withoutParams->hasParameters());
    }

    public function testEvaluationFallsBackGracefully(): void
    {
        // Test that evaluation doesn't throw for non-existent functions
        $condition = RouteCondition::fromWordPressTag('non_existent_function');
        $result = $condition->evaluate();

        $this->assertFalse($result);
    }

    public function testDifferentParametersGenerateDifferentIdentifiers(): void
    {
        $condition1 = RouteCondition::fromWordPressTag('is_page', ['about']);
        $condition2 = RouteCondition::fromWordPressTag('is_page', ['contact']);

        $this->assertNotEquals(
            $condition1->toUniqueIdentifier(),
            $condition2->toUniqueIdentifier()
        );
    }

    public function testFromWordPressTagWithResolver(): void
    {
        $resolver = $this->createMockResolver();
        
        // Test alias resolution with resolver
        $condition = RouteCondition::fromWordPressTag('page', ['about'], $resolver);
        
        $this->assertEquals('wordpress', $condition->getType());
        $this->assertEquals('is_page', $condition->getCondition());
        $this->assertEquals(['about'], $condition->getParameters());
    }

    public function testCreateValidatedMethod(): void
    {
        $resolver = $this->createMockResolver();
        
        $condition = RouteCondition::createValidated('page', ['about'], $resolver);
        
        $this->assertEquals('wordpress', $condition->getType());
        $this->assertEquals('is_page', $condition->getCondition());
        $this->assertEquals(['about'], $condition->getParameters());
    }

    public function testInvalidConditionThrowsException(): void
    {
        $resolver = $this->createMockResolver(false); // Invalid condition
        
        $this->expectException(InvalidRouteConditionException::class);
        $this->expectExceptionMessage("WordPress condition 'invalid' (resolved to 'invalid') is not available.");
        
        RouteCondition::fromWordPressTag('invalid', [], $resolver);
    }

    public function testInvalidParametersThrowException(): void
    {
        $resolver = $this->createMockResolver(true, false); // Valid condition, invalid params
        
        $this->expectException(InvalidRouteConditionException::class);
        $this->expectExceptionMessage("Invalid parameters for WordPress condition 'is_page'.");
        
        RouteCondition::fromWordPressTag('page', ['invalid-param'], $resolver);
    }

    public function testBackwardCompatibilityWithoutResolver(): void
    {
        // Test that existing code without resolver still works
        $condition = RouteCondition::fromWordPressTag('is_page', ['about']);
        
        $this->assertEquals('wordpress', $condition->getType());
        $this->assertEquals('is_page', $condition->getCondition());
        $this->assertEquals(['about'], $condition->getParameters());
    }

    public function testAliasResolutionWithResolver(): void
    {
        $config = [
            'conditions' => [
                'is_page' => 'page',
                'is_category' => ['category', 'cat'],
            ],
        ];
        
        $resolver = new ConditionalTagsResolver($config);
        
        // Test basic alias
        $condition1 = RouteCondition::fromWordPressTag('page', [], $resolver);
        $this->assertEquals('is_page', $condition1->getCondition());
        
        // Test array alias
        $condition2 = RouteCondition::fromWordPressTag('category', [], $resolver);
        $this->assertEquals('is_category', $condition2->getCondition());
        
        $condition3 = RouteCondition::fromWordPressTag('cat', [], $resolver);
        $this->assertEquals('is_category', $condition3->getCondition());
    }

    private function createMockResolver(bool $hasCondition = true, bool $validParams = true): ConditionResolverInterface
    {
        $resolver = $this->createMock(ConditionResolverInterface::class);
        
        $resolver->method('resolveAlias')
            ->willReturnCallback(function ($alias) {
                // Simple alias resolution for testing
                return $alias === 'page' ? 'is_page' : $alias;
            });
            
        $resolver->method('hasCondition')
            ->willReturn($hasCondition);
            
        $resolver->method('validateParameters')
            ->willReturn($validParams);
            
        return $resolver;
    }
}