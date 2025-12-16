<?php

declare(strict_types=1);

namespace Tests\Unit\Logging\Domain\Enums;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pollora\Logging\Domain\Enums\LogLevel;
use Psr\Log\LogLevel as PsrLogLevel;

/**
 * Test case for LogLevel enum.
 *
 * @covers \Pollora\Logging\Domain\Enums\LogLevel
 */
#[CoversClass(LogLevel::class)]
final class LogLevelTest extends TestCase
{
    #[Test]
    public function it_has_correct_psr3_values(): void
    {
        $this->assertSame(PsrLogLevel::EMERGENCY, LogLevel::EMERGENCY->value);
        $this->assertSame(PsrLogLevel::ALERT, LogLevel::ALERT->value);
        $this->assertSame(PsrLogLevel::CRITICAL, LogLevel::CRITICAL->value);
        $this->assertSame(PsrLogLevel::ERROR, LogLevel::ERROR->value);
        $this->assertSame(PsrLogLevel::WARNING, LogLevel::WARNING->value);
        $this->assertSame(PsrLogLevel::NOTICE, LogLevel::NOTICE->value);
        $this->assertSame(PsrLogLevel::INFO, LogLevel::INFO->value);
        $this->assertSame(PsrLogLevel::DEBUG, LogLevel::DEBUG->value);
    }

    #[Test]
    public function it_returns_correct_priorities(): void
    {
        $this->assertSame(800, LogLevel::EMERGENCY->priority());
        $this->assertSame(700, LogLevel::ALERT->priority());
        $this->assertSame(600, LogLevel::CRITICAL->priority());
        $this->assertSame(500, LogLevel::ERROR->priority());
        $this->assertSame(400, LogLevel::WARNING->priority());
        $this->assertSame(300, LogLevel::NOTICE->priority());
        $this->assertSame(200, LogLevel::INFO->priority());
        $this->assertSame(100, LogLevel::DEBUG->priority());
    }

    #[Test]
    public function it_compares_criticality_correctly(): void
    {
        $this->assertTrue(LogLevel::EMERGENCY->isMoreCriticalThan(LogLevel::ERROR));
        $this->assertTrue(LogLevel::ERROR->isMoreCriticalThan(LogLevel::WARNING));
        $this->assertTrue(LogLevel::WARNING->isMoreCriticalThan(LogLevel::DEBUG));

        $this->assertFalse(LogLevel::DEBUG->isMoreCriticalThan(LogLevel::ERROR));
        $this->assertFalse(LogLevel::WARNING->isMoreCriticalThan(LogLevel::CRITICAL));
        $this->assertFalse(LogLevel::ERROR->isMoreCriticalThan(LogLevel::ERROR));
    }

    #[Test]
    public function it_returns_levels_ordered_by_priority(): void
    {
        $expected = [
            LogLevel::EMERGENCY,
            LogLevel::ALERT,
            LogLevel::CRITICAL,
            LogLevel::ERROR,
            LogLevel::WARNING,
            LogLevel::NOTICE,
            LogLevel::INFO,
            LogLevel::DEBUG,
        ];

        $this->assertEquals($expected, LogLevel::allByPriority());
    }

    #[Test]
    public function priority_order_is_descending(): void
    {
        $levels = LogLevel::allByPriority();
        $priorities = array_map(fn (LogLevel $level) => $level->priority(), $levels);

        $sortedPriorities = $priorities;
        rsort($sortedPriorities);

        $this->assertEquals($sortedPriorities, $priorities);
    }
}
