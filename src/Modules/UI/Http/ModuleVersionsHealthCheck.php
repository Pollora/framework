<?php

declare(strict_types=1);

namespace Pollora\Modules\UI\Http;

use Pollora\Modules\Application\Services\ModuleVersions;

/**
 * Site Health: "Pollora modules are up to date", for the modules installed by Composer.
 */
class ModuleVersionsHealthCheck
{
    public function __construct(private readonly ModuleVersions $versions) {}

    /**
     * @param  array<string, mixed>  $tests
     * @return array<string, mixed>
     */
    public function addTests(array $tests): array
    {
        $tests['direct']['pollora_modules_update'] = [
            'label' => __('Pollora modules are up to date', 'pollora'),
            'test' => $this->test(...),
        ];

        return $tests;
    }

    /**
     * @return array{label: string, status: string, badge: array{label: string, color: string}, description: string, test: string}
     */
    public function test(): array
    {
        $outdated = array_filter($this->versions->all(), fn (array $version): bool => $version['update']);

        if ($outdated === []) {
            return [
                'label' => __('Pollora modules are up to date', 'pollora'),
                'status' => 'good',
                'badge' => ['label' => 'Pollora', 'color' => 'blue'],
                'description' => '<p>'.esc_html__('Every module installed by Composer runs its latest known version. Local modules have no version and are not checked.', 'pollora').'</p>',
                'test' => 'pollora_modules_update',
            ];
        }

        $items = '';

        foreach ($outdated as $name => $version) {
            /* translators: 1: module, 2: installed version, 3: latest version, 4: Composer package */
            $items .= '<li>'.esc_html(sprintf(__('%1$s %2$s → %3$s (composer update %4$s)', 'pollora'), $name, $version['version'], (string) $version['latest'], $version['package'])).'</li>';
        }

        return [
            /* translators: %d: number of outdated modules */
            'label' => sprintf(_n('%d Pollora module has an update', '%d Pollora modules have an update', count($outdated), 'pollora'), count($outdated)),
            'status' => 'recommended',
            'badge' => ['label' => 'Pollora', 'color' => 'orange'],
            'description' => '<ul>'.$items.'</ul>',
            'test' => 'pollora_modules_update',
        ];
    }
}
