<?php

declare(strict_types=1);

namespace Tests\Unit\Logging\Infrastructure\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pollora\Logging\Infrastructure\Services\FallbackLogger;

/**
 * Test case for FallbackLogger.
 *
 * @covers \Pollora\Logging\Infrastructure\Services\FallbackLogger
 */
#[CoversClass(FallbackLogger::class)]
final class FallbackLoggerTest extends TestCase
{
    private string $originalLogFile;

    private string $tempLogFile;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a temporary log file for testing
        $this->tempLogFile = tempnam(sys_get_temp_dir(), 'pollora_test_');
        $this->originalLogFile = ini_get('error_log');
        ini_set('error_log', $this->tempLogFile);
    }

    protected function tearDown(): void
    {
        // Restore original error_log setting
        if ($this->originalLogFile) {
            ini_set('error_log', $this->originalLogFile);
        } else {
            ini_restore('error_log');
        }

        // Clean up temp file
        if (file_exists($this->tempLogFile)) {
            unlink($this->tempLogFile);
        }

        parent::tearDown();
    }

    private function readLogEntries(): array
    {
        if (! file_exists($this->tempLogFile)) {
            return [];
        }

        $content = file_get_contents($this->tempLogFile);
        if (empty($content)) {
            return [];
        }

        return array_filter(explode("\n", trim($content)));
    }

    #[Test]
    public function it_logs_messages_with_error_log(): void
    {
        $logger = new FallbackLogger;

        $logger->log('error', 'Test message', ['key' => 'value']);

        $logs = $this->readLogEntries();
        $this->assertCount(1, $logs);
        $logEntry = $logs[0];

        $this->assertStringContainsString('[Pollora]', $logEntry);
        $this->assertStringContainsString('[ERROR]', $logEntry);
        $this->assertStringContainsString('Test message', $logEntry);
        $this->assertStringContainsString('{"key":"value"}', $logEntry);
    }

    #[Test]
    public function it_logs_without_context(): void
    {
        $logger = new FallbackLogger;

        $logger->log('info', 'Simple message');

        $logs = $this->readLogEntries();
        $this->assertCount(1, $logs);
        $logEntry = $logs[0];

        $this->assertStringContainsString('[INFO]', $logEntry);
        $this->assertStringContainsString('Simple message', $logEntry);
        $this->assertStringNotContainsString('{', $logEntry);
    }

    #[Test]
    public function it_logs_with_module_prefix(): void
    {
        $logger = new FallbackLogger;

        $logger->logWithModule('warning', 'Module message', ['pollora_framework' => true]);

        $logs = $this->readLogEntries();
        $this->assertCount(1, $logs);
        $logEntry = $logs[0];

        $this->assertStringContainsString('[WARNING]', $logEntry);
        $this->assertStringContainsString('Module message', $logEntry);
        $this->assertStringContainsString('pollora_framework', $logEntry);
    }

    #[Test]
    public function it_returns_correct_channel_name(): void
    {
        $logger = new FallbackLogger;

        $this->assertSame('error_log', $logger->getChannelName());
    }

    #[Test]
    public function it_handles_debug_enabled_flag(): void
    {
        $debugLogger = new FallbackLogger(debugEnabled: true);
        $nonDebugLogger = new FallbackLogger(debugEnabled: false);

        $this->assertTrue($debugLogger->isDebugEnabled());
        $this->assertFalse($nonDebugLogger->isDebugEnabled());
    }

    #[Test]
    public function it_formats_timestamp_correctly(): void
    {
        $logger = new FallbackLogger;

        $logger->log('debug', 'Timestamped message');

        $logs = $this->readLogEntries();
        $this->assertCount(1, $logs);
        $logEntry = $logs[0];

        // Check for timestamp format: [YYYY-MM-DD HH:MM:SS] (may be preceded by error_log timestamp)
        $this->assertMatchesRegularExpression('/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]/', $logEntry);
    }

    #[Test]
    public function it_handles_empty_context_array(): void
    {
        $logger = new FallbackLogger;

        $logger->log('notice', 'Message', []);

        $logs = $this->readLogEntries();
        $this->assertCount(1, $logs);
        $logEntry = $logs[0];

        $this->assertStringContainsString('[NOTICE]', $logEntry);
        $this->assertStringNotContainsString('{}', $logEntry);
    }

    #[Test]
    public function it_handles_complex_context_data(): void
    {
        $logger = new FallbackLogger;

        $context = [
            'array' => [1, 2, 3],
            'null' => null,
            'bool' => true,
            'string' => 'test/path',
        ];

        $logger->log('alert', 'Complex context', $context);

        $logs = $this->readLogEntries();
        $this->assertCount(1, $logs);
        $logEntry = $logs[0];

        $this->assertStringContainsString('[ALERT]', $logEntry);
        $this->assertStringContainsString('"array":[1,2,3]', $logEntry);
        $this->assertStringContainsString('"null":null', $logEntry);
        $this->assertStringContainsString('"bool":true', $logEntry);
        $this->assertStringContainsString('test/path', $logEntry);
    }

    #[Test]
    public function it_uses_psr3_methods(): void
    {
        $logger = new FallbackLogger;

        $logger->emergency('Emergency message');
        $logger->alert('Alert message');
        $logger->critical('Critical message');
        $logger->error('Error message');
        $logger->warning('Warning message');
        $logger->notice('Notice message');
        $logger->info('Info message');
        $logger->debug('Debug message');

        $logs = $this->readLogEntries();
        $this->assertCount(8, $logs);

        $levels = ['EMERGENCY', 'ALERT', 'CRITICAL', 'ERROR', 'WARNING', 'NOTICE', 'INFO', 'DEBUG'];
        foreach ($levels as $index => $level) {
            $this->assertStringContainsString("[{$level}]", $logs[$index]);
        }
    }
}
