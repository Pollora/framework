<?php

declare(strict_types=1);

namespace Pollen\Gutenberg\Helpers;

class PatternValidator
{
    public function isValid(array $patternData, string $file): bool
    {
        if (! $this->isValidPattern($patternData)) {
            $this->logPatternError($file, $patternData);

            return false;
        }

        return ! $this->isPatternRegistered($patternData['slug']);
    }

    private function isValidPattern(array $patternData): bool
    {
        return ! empty($patternData['slug']) && ! empty($patternData['title']);
    }

    private function isPatternRegistered(string $slug): bool
    {
        return \WP_Block_Patterns_Registry::get_instance()->is_registered($slug);
    }

    protected function logPatternError(string $file, array $patternData): void
    {
        $message = empty($patternData['slug'])
            ? __('Could not register file "%s" as a block pattern ("Slug" field missing)')
            : __('Could not register file "%s" as a block pattern ("Title" field missing)');

        _doing_it_wrong('_register_theme_block_patterns', sprintf($message, $file), '6.0.0');
    }
}
