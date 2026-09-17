<?php

declare(strict_types=1);

namespace Pollora\VersionCheck\UI\Http;

use Pollora\VersionCheck\Domain\Services\VersionComparator;

/**
 * Integrates Pollora version information into WordPress Site Health.
 *
 * Provides two integration points:
 * - **Debug Information** (`Tools > Site Health > Info`): Displays the installed
 *   and latest Pollora version in a dedicated "Pollora" section.
 * - **Status Tests** (`Tools > Site Health > Status`): Adds a test that reports
 *   whether Pollora is up to date, with actionable links to the changelog.
 *
 * @see https://developer.wordpress.org/reference/hooks/debug_information/
 * @see https://developer.wordpress.org/reference/hooks/site_status_tests/
 */
class SiteHealthCheck
{
    public function __construct(
        private readonly VersionComparator $comparator
    ) {}

    /**
     * Add a "Pollora" section to the Site Health debug information page.
     *
     * Displays the installed version, latest available version, and whether
     * the framework is up to date.
     *
     * @param  array  $info  Existing debug information sections
     * @return array Modified debug information with the Pollora section appended
     */
    public function addDebugInfo(array $info): array
    {
        $current = $this->comparator->getCurrentVersion() ?? 'Unknown';
        $latest = $this->comparator->getLatestVersion() ?? 'Unable to check';

        $info['pollora'] = [
            'label' => 'Pollora',
            'fields' => [
                'version' => [
                    'label' => __('Installed version', 'pollora'),
                    'value' => $current,
                ],
                'latest_version' => [
                    'label' => __('Latest version', 'pollora'),
                    'value' => $latest,
                ],
                'up_to_date' => [
                    'label' => __('Up to date', 'pollora'),
                    'value' => $this->comparator->isUpdateAvailable() ? __('No', 'pollora') : __('Yes', 'pollora'),
                ],
            ],
        ];

        return $info;
    }

    /**
     * Register the Pollora version check as a direct Site Health status test.
     *
     * The test appears under the "Status" tab and calls {@see testVersionStatus()}
     * to determine whether the framework needs updating.
     *
     * @param  array  $tests  Existing status tests
     * @return array Modified status tests with the Pollora check appended
     */
    public function addTests(array $tests): array
    {
        $tests['direct']['pollora_update'] = [
            'label' => __('Pollora is up to date', 'pollora'),
            'test' => $this->testVersionStatus(...),
        ];

        return $tests;
    }

    /**
     * Execute the version status test for Site Health.
     *
     * Returns one of three results:
     * - **good** (blue badge): Pollora is up to date
     * - **recommended** (orange badge): A newer version is available
     * - **recommended** (orange badge): Version status could not be determined
     *
     * @return array{label: string, status: string, badge: array{label: string, color: string}, description: string, test: string, actions?: string} Test result
     */
    public function testVersionStatus(): array
    {
        $current = $this->comparator->getCurrentVersion();
        $latest = $this->comparator->getLatestVersion();

        if ($current === null || $latest === null) {
            return [
                'label' => __('Unable to determine Pollora version status', 'pollora'),
                'status' => 'recommended',
                'badge' => [
                    'label' => 'Pollora',
                    'color' => 'orange',
                ],
                'description' => '<p>'.__('Could not determine the current or latest Pollora version. Check your internet connection.', 'pollora').'</p>',
                'test' => 'pollora_update',
            ];
        }

        if (! $this->comparator->isUpdateAvailable()) {
            return [
                'label' => __('Pollora is up to date', 'pollora'),
                'status' => 'good',
                'badge' => [
                    'label' => 'Pollora',
                    'color' => 'blue',
                ],
                /* translators: %s: currently installed version */
                'description' => '<p>'.sprintf(__('You are running Pollora %s, which is the latest version.', 'pollora'), $current).'</p>',
                'test' => 'pollora_update',
            ];
        }

        return [
            /* translators: %s: latest available version */
            'label' => sprintf(__('Pollora %s is available', 'pollora'), $latest),
            'status' => 'recommended',
            'badge' => [
                'label' => 'Pollora',
                'color' => 'orange',
            ],
            'description' => '<p>'.sprintf(
                /* translators: 1: current version, 2: latest version, 3: opening link tag, 4: closing link tag */
                __('You are running Pollora %1$s. The latest version is %2$s. %3$sView changelog%4$s.', 'pollora'),
                $current,
                $latest,
                '<a href="'.esc_url('https://github.com/Pollora/framework/releases/tag/v'.$latest).'" target="_blank" rel="noopener noreferrer">',
                '</a>'
            ).'</p>',
            'actions' => '<p><a href="'.esc_url('https://github.com/Pollora/framework/blob/main/CHANGELOG.md').'" target="_blank" rel="noopener noreferrer">'.__('Learn more about updating Pollora', 'pollora').'</a></p>',
            'test' => 'pollora_update',
        ];
    }
}
