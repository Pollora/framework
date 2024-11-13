<?php

declare(strict_types=1);

namespace Pollora\WordPress;

use Illuminate\Support\Facades\DB;
use Pollora\Support\Facades\Filter;

trait QueryTrait
{
    public function isDatabaseConfigured()
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
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * Check if WordPress is installed by verifying core tables exist and have content
     */
    private function isWordPressInstalled(): bool
    {
        if (function_exists('is_blog_installed')) {
            return is_blog_installed();
        }

        try {
            return DB::table('options')
                ->where('option_name', 'siteurl')
                ->exists();

        } catch (\Exception $e) {
            return false;
        }
    }

    protected function setupWordPressQuery(): void
    {
        wp();
        do_action('template_redirect');

        if ($this->shouldExitEarly()) {
            exit;
        }
    }

    private function shouldExitEarly(): bool
    {
        if ($this->isHeadRequest()) {
            return true;
        }
        if ($this->isRobots()) {
            return true;
        }
        if ($this->isFavicon()) {
            return true;
        }
        if ($this->isFeed()) {
            return true;
        }

        return (bool) $this->isTrackback();
    }

    private function isHeadRequest(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'HEAD' && Filter::apply('exit_on_http_head', true);
    }

    private function isRobots(): bool
    {
        if (is_robots()) {
            do_action('do_robots');

            return true;
        }

        return false;
    }

    private function isFavicon(): bool
    {
        if (is_favicon()) {
            do_action('do_favicon');

            return true;
        }

        return false;
    }

    private function isFeed(): bool
    {
        if (is_feed()) {
            do_feed();

            return true;
        }

        return false;
    }

    private function isTrackback(): bool
    {
        if (is_trackback()) {
            require ABSPATH.'wp-trackback.php';

            return true;
        }

        return false;
    }
}
