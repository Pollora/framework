<?php

declare(strict_types=1);

namespace Pollora\WordPress;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Pollora\Support\Facades\Filter;

trait QueryTrait
{
    public function isDatabaseConfigured(): bool
    {
        $config = DB::connection()->getConfig();

        $dbSettingsFilled = $config['driver'] === 'mysql'
            && isset($config['host'])
            && isset($config['username'])
            && isset($config['password'])
            && isset($config['database']);

        if (! $dbSettingsFilled) {
            return false;
        }

        try {
            DB::connection()->getPdo();
        } catch (\Exception) {
            return false;
        }

        return true;
    }

    /**
     * Check if WordPress is installed by verifying core tables exist and have content
     */
    /**
     * Check if WordPress is installed by verifying the presence of core database tables and required content.
     *
     * @return bool True if WordPress is installed, false otherwise.
     */
    private function isWordPressInstalled(): bool
    {
        // Use the built-in WordPress function if available
        if (function_exists('is_blog_installed')) {
            return is_blog_installed();
        }
        
        if (!$this->isDatabaseConfigured()) {
            return false;
        }

        // Fallback to direct database check
        try {
            return Schema::hasTable('options') && DB::table('options')
                ->where('option_name', 'siteurl')
                ->exists();
        } catch (Exception $e) {
            return false;
        }

        return false;
    }

    /**
     * Run the WordPress bootstrap process and load the default template loader.
     *
     * @return void
     */
    protected function runWp(): void
    {
        if (! function_exists('wp')) {
            throw new Exception('The WordPress core functions are not available. Ensure WordPress is loaded.');
        }

        wp();
        require_once ABSPATH . WPINC . '/template-loader.php';
    }
}
