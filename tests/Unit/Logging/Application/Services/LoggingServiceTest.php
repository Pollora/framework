<?php

declare(strict_types=1);

namespace Tests\Unit\Logging\Application\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pollora\Logging\Application\Services\LoggingService;
use Pollora\Logging\Domain\Contracts\LoggerInterface;
use Pollora\Logging\Domain\ValueObjects\LogContext;
use RuntimeException;

/**
 * Test case for LoggingService.
 *
 * @covers \Pollora\Logging\Application\Services\LoggingService
 */
#[CoversClass(LoggingService::class)]
final class LoggingServiceTest extends TestCase
{
    private LoggerInterface $logger;

    private LoggingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->service = new LoggingService($this->logger);
    }

    #[Test]
    public function it_logs_error_with_context_and_exception(): void
    {
        $exception = new RuntimeException('Test exception');
        $context = new LogContext('TestModule');

        $this->logger->expects($this->once())
            ->method('logWithModule')
            ->with(
                'error',
                'Test error message',
                $this->callback(function (array $contextArray) use ($exception) {
                    return $contextArray['pollora_module'] === 'TestModule'
                        && $contextArray['exception'] === $exception;
                })
            );

        $this->service->error('Test error message', $context, $exception);
    }

    #[Test]
    public function it_logs_warning_without_context(): void
    {
        $this->logger->expects($this->once())
            ->method('logWithModule')
            ->with('warning', 'Warning message', []);

        $this->service->warning('Warning message');
    }

    #[Test]
    public function it_logs_info_with_context(): void
    {
        $context = new LogContext('InfoModule', 'InfoClass', 'infoMethod');

        $this->logger->expects($this->once())
            ->method('logWithModule')
            ->with(
                'info',
                'Info message',
                $this->callback(function (array $contextArray) {
                    return $contextArray['pollora_module'] === 'InfoModule'
                        && $contextArray['class'] === 'InfoClass'
                        && $contextArray['method'] === 'infoMethod';
                })
            );

        $this->service->info('Info message', $context);
    }

    #[Test]
    public function it_skips_debug_when_not_enabled(): void
    {
        $this->logger->method('isDebugEnabled')->willReturn(false);
        $this->logger->expects($this->never())->method('logWithModule');

        $this->service->debug('This should not be logged');
    }

    #[Test]
    public function it_logs_debug_when_enabled(): void
    {
        $this->logger->method('isDebugEnabled')->willReturn(true);
        $this->logger->expects($this->once())
            ->method('logWithModule')
            ->with('debug', 'Debug message', []);

        $this->service->debug('Debug message');
    }

    #[Test]
    public function it_logs_notice(): void
    {
        $this->logger->expects($this->once())
            ->method('logWithModule')
            ->with('notice', 'Notice message', []);

        $this->service->notice('Notice message');
    }

    #[Test]
    public function it_logs_critical_with_exception(): void
    {
        $exception = new RuntimeException('Critical error');

        $this->logger->expects($this->once())
            ->method('logWithModule')
            ->with(
                'critical',
                'Critical message',
                $this->callback(function (array $context) use ($exception) {
                    return $context['exception'] === $exception;
                })
            );

        $this->service->critical('Critical message', null, $exception);
    }

    #[Test]
    public function it_returns_underlying_logger(): void
    {
        $this->assertSame($this->logger, $this->service->getLogger());
    }

    #[Test]
    public function it_delegates_debug_enabled_check(): void
    {
        $this->logger->method('isDebugEnabled')->willReturn(true);

        $this->assertTrue($this->service->isDebugEnabled());
    }

    #[Test]
    public function it_delegates_channel_name(): void
    {
        $this->logger->method('getChannelName')->willReturn('test-channel');

        $this->assertSame('test-channel', $this->service->getChannelName());
    }

    #[Test]
    public function it_merges_exception_into_context(): void
    {
        $exception = new RuntimeException('Test');
        $context = new LogContext('Test', extra: ['key' => 'value']);

        $this->logger->expects($this->once())
            ->method('logWithModule')
            ->with(
                'error',
                'Error with context and exception',
                $this->callback(function (array $contextArray) use ($exception) {
                    return $contextArray['exception'] === $exception
                        && $contextArray['key'] === 'value'
                        && $contextArray['pollora_module'] === 'Test';
                })
            );

        $this->service->error('Error with context and exception', $context, $exception);
    }

    #[Test]
    public function it_does_not_override_existing_exception_in_context(): void
    {
        $contextException = new RuntimeException('Context exception');
        $paramException = new RuntimeException('Param exception');

        $context = new LogContext('Test', exception: $contextException);

        $this->logger->expects($this->once())
            ->method('logWithModule')
            ->with(
                'error',
                'Error message',
                $this->callback(function (array $contextArray) use ($contextException) {
                    return $contextArray['exception'] === $contextException;
                })
            );

        $this->service->error('Error message', $context, $paramException);
    }
}
