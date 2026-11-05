<?php

declare(strict_types=1);

namespace Pollora\Plugin\UI\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Activate WordPress plugins, with WordPress fully loaded.
 *
 * Meant to run in its own process: during a first install the process that
 * installs WordPress only has it partly loaded (SHORTINIT), which cannot
 * activate a plugin and run its activation hook.
 */
#[Description('Activate WordPress plugins')]
#[Signature('pollora:plugin:activate {plugins* : Plugin files relative to the plugins directory, e.g. woocommerce/woocommerce.php}')]
class PluginActivateCommand extends Command
{
    public function handle(): int
    {
        if (! $this->isWordPressFullyLoaded()) {
            $this->error('WordPress is not fully loaded: the plugins cannot be activated. Run "wp plugin activate" instead.');

            return self::FAILURE;
        }

        $status = self::SUCCESS;

        foreach ((array) $this->argument('plugins') as $plugin) {
            $result = $this->activatePlugin((string) $plugin);

            if (is_wp_error($result)) {
                $this->error(sprintf('Failed to activate "%s": %s', $plugin, $result->get_error_message()));
                $status = self::FAILURE;

                continue;
            }

            $this->info(sprintf('Plugin "%s" activated.', $plugin));
        }

        return $status;
    }

    protected function isWordPressFullyLoaded(): bool
    {
        return function_exists('activate_plugin') && did_action('plugins_loaded') > 0;
    }

    /**
     * @return \WP_Error|null
     */
    protected function activatePlugin(string $pluginFile): mixed
    {
        return activate_plugin($pluginFile);
    }
}
