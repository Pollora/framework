<?php

declare(strict_types=1);

namespace Tests\Unit\Route\Domain\Models;

use PHPUnit\Framework\TestCase;
use Pollora\Route\Domain\Models\SpecialRequest;

/**
 * @covers \Pollora\Route\Domain\Models\SpecialRequest
 */
class SpecialRequestTest extends TestCase
{
    public function testFromContextWithRobotsRequest(): void
    {
        $context = ['uri' => '/robots.txt'];

        $specialRequest = SpecialRequest::fromContext($context);

        $this->assertNotNull($specialRequest);
        $this->assertEquals('robots', $specialRequest->getType());
        $this->assertEquals('is_robots', $specialRequest->getConditionFunction());
    }

    public function testFromContextWithFaviconRequest(): void
    {
        $context = ['uri' => '/favicon.ico'];

        $specialRequest = SpecialRequest::fromContext($context);

        $this->assertNotNull($specialRequest);
        $this->assertEquals('favicon', $specialRequest->getType());
        $this->assertEquals('is_favicon', $specialRequest->getConditionFunction());
    }

    public function testFromContextWithFeedRequest(): void
    {
        $context = ['uri' => '/feed'];

        $specialRequest = SpecialRequest::fromContext($context);

        $this->assertNotNull($specialRequest);
        $this->assertEquals('feed', $specialRequest->getType());
        $this->assertEquals('is_feed', $specialRequest->getConditionFunction());
    }

    public function testFromContextWithTrackbackRequest(): void
    {
        $context = ['uri' => '/trackback'];

        $specialRequest = SpecialRequest::fromContext($context);

        $this->assertNotNull($specialRequest);
        $this->assertEquals('trackback', $specialRequest->getType());
        $this->assertEquals('is_trackback', $specialRequest->getConditionFunction());
    }

    public function testFromContextWithNormalRequest(): void
    {
        $context = ['uri' => '/normal-page'];

        $specialRequest = SpecialRequest::fromContext($context);

        $this->assertNull($specialRequest);
    }

    public function testGetSupportedTypes(): void
    {
        $types = SpecialRequest::getSupportedTypes();

        $this->assertIsArray($types);
        $this->assertContains('robots', $types);
        $this->assertContains('favicon', $types);
        $this->assertContains('feed', $types);
        $this->assertContains('trackback', $types);
    }

    public function testCreate(): void
    {
        $specialRequest = SpecialRequest::create('robots', ['uri' => '/robots.txt']);

        $this->assertNotNull($specialRequest);
        $this->assertEquals('robots', $specialRequest->getType());
        $this->assertEquals('is_robots', $specialRequest->getConditionFunction());
    }

    public function testCreateInvalidType(): void
    {
        $specialRequest = SpecialRequest::create('invalid_type');

        $this->assertNull($specialRequest);
    }

    public function testIsTypeSupported(): void
    {
        $this->assertTrue(SpecialRequest::isTypeSupported('robots'));
        $this->assertTrue(SpecialRequest::isTypeSupported('favicon'));
        $this->assertTrue(SpecialRequest::isTypeSupported('feed'));
        $this->assertFalse(SpecialRequest::isTypeSupported('invalid'));
    }

    public function testGetPriority(): void
    {
        $robots = SpecialRequest::create('robots');
        $favicon = SpecialRequest::create('favicon');
        $feed = SpecialRequest::create('feed');

        $this->assertEquals(2000, $robots->getPriority());
        $this->assertEquals(1900, $favicon->getPriority());
        $this->assertEquals(1800, $feed->getPriority());
    }

    public function testShouldUseWordPressDefault(): void
    {
        $robots = SpecialRequest::create('robots');
        $xmlrpc = SpecialRequest::create('xmlrpc');
        $pingback = SpecialRequest::create('pingback');

        $this->assertFalse($robots->shouldUseWordPressDefault());
        $this->assertTrue($xmlrpc->shouldUseWordPressDefault());
        $this->assertTrue($pingback->shouldUseWordPressDefault());
    }

    public function testGetExpectedContentType(): void
    {
        $robots = SpecialRequest::create('robots');
        $favicon = SpecialRequest::create('favicon');
        $feed = SpecialRequest::create('feed');

        $this->assertEquals('text/plain', $robots->getExpectedContentType());
        $this->assertEquals('image/x-icon', $favicon->getExpectedContentType());
        $this->assertEquals('application/rss+xml', $feed->getExpectedContentType());
    }

    public function testToRouteCondition(): void
    {
        $specialRequest = SpecialRequest::create('robots');
        $condition = $specialRequest->toRouteCondition();

        $this->assertEquals('wordpress', $condition->getType());
        $this->assertEquals('is_robots', $condition->getCondition());
    }

    public function testToArray(): void
    {
        $specialRequest = SpecialRequest::create('robots', ['uri' => '/robots.txt']);
        $array = $specialRequest->toArray();

        $this->assertEquals('robots', $array['type']);
        $this->assertEquals('is_robots', $array['condition_function']);
        $this->assertEquals(2000, $array['priority']);
        $this->assertEquals('text/plain', $array['content_type']);
        $this->assertFalse($array['use_wordpress_default']);
        $this->assertEquals(['uri' => '/robots.txt'], $array['context']);
    }

    public function testGetContext(): void
    {
        $context = ['uri' => '/robots.txt', 'method' => 'GET'];
        $specialRequest = SpecialRequest::create('robots', $context);

        $this->assertEquals($context, $specialRequest->getContext());
    }
}