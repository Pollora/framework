<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Option;

use Pollora\Events\WordPress\AbstractEventDispatcher;

/**
 * Event dispatcher for WordPress option-related events.
 *
 * This class handles the dispatching of Laravel events for WordPress option actions
 * such as updates to various WordPress settings and options.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class OptionEventDispatcher extends AbstractEventDispatcher
{
    /**
     * WordPress actions to listen to.
     *
     * @var array<string>
     */
    protected array $actions = [
        'updated_option',
    ];

    /**
     * List of options to ignore.
     *
     * @var array<string>
     */
    protected array $ignoredOptions = [
        'cron',
        'doing_cron',
        '_transient_',
        '_site_transient_',
        'theme_mods_',
    ];

    /**
     * Handle option update.
     *
     * @param  string  $option  Option name
     * @param  mixed  $oldValue  Old option value
     * @param  mixed  $value  New option value
     */
    public function handleUpdatedOption(string $option, mixed $oldValue, mixed $value): void
    {
        // Skip if option should be ignored
        if ($this->shouldIgnoreOption($option)) {
            return;
        }

        // Skip if values are identical
        if ($oldValue === $value) {
            return;
        }

        $this->dispatch(OptionUpdated::class, [$option, $oldValue, $value]);
    }

    /**
     * Check if an option should be ignored.
     *
     * @param  string  $option  Option name to check
     */
    protected function shouldIgnoreOption(string $option): bool
    {
        foreach ($this->ignoredOptions as $ignoredOption) {
            if (str_starts_with($option, $ignoredOption)) {
                return true;
            }
        }

        return false;
    }
}
