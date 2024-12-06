<?php

declare(strict_types=1);

namespace Pollora\Services\WordPress\Database;

use Illuminate\Support\Facades\File;

class EnvironmentFileHandler
{
    /**
     * Update environment file with new values
     */
    public function updateEnvFile(array $replacements): void
    {
        $envPath = base_path('.env');

        $envContent = File::get(base_path(File::exists('.env') ? '.env' : '.env.example'));

        $updatedContent = $this->processEnvContent($envContent, $replacements);

        File::put($envPath, $updatedContent);
    }

    /**
     * Process environment file content and replace values
     */
    private function processEnvContent(string $content, array $replacements): string
    {
        $lines = explode("\n", $content);
        $processed = [];

        foreach ($lines as $line) {
            $line = $this->processLine($line, $replacements);
            $processed[] = $line;
        }

        return implode("\n", $processed);
    }

    /**
     * Process a single line of the environment file
     */
    private function processLine(string $line, array $replacements): string
    {
        // Skip empty lines or comments that don't contain variable definitions
        if ($line === '' || $line === '0' || (str_starts_with(trim($line), '#') && in_array(preg_match('/^#\s*([^=]+)=/', $line), [0, false], true))) {
            return $line;
        }

        // Extract variable name, handling both commented and uncommented lines
        if (in_array(preg_match('/^#?\s*([^=]+)=(.*)$/', $line, $matches), [0, false], true)) {
            return $line;
        }

        $key = trim($matches[1]);

        // If this key exists in our replacements, update the line
        if (array_key_exists($key, $replacements)) {
            return "{$key}={$replacements[$key]}";
        }

        return $line;
    }
}
