<?php

declare(strict_types=1);

namespace Pollora\BlockPattern\Infrastructure\Adapters;

use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Pollora\BlockPattern\Domain\Contracts\PatternDataExtractorInterface;
use Pollora\BlockPattern\Domain\Models\PatternFileData;
use Pollora\BlockPattern\Domain\Support\PatternConstants;
use Pollora\Collection\Domain\Contracts\CollectionFactoryInterface;

/**
 * WordPress implementation of PatternDataExtractorInterface.
 *
 * This is an adapter in hexagonal architecture that connects
 * our domain to WordPress for pattern data extraction.
 */
class WordPressPatternDataExtractor implements PatternDataExtractorInterface
{
    /**
     * Create a new WordPress pattern data extractor instance.
     */
    public function __construct(
        private readonly CollectionFactoryInterface $collectionFactory
    ) {}

    /**
     * {@inheritdoc}
     */
    public function extractFromFile(string $file): PatternFileData
    {
        $headers = [];

        if (function_exists('get_file_data')) {
            $headers = \get_file_data($file, PatternConstants::DEFAULT_HEADERS);
        }

        return new PatternFileData($file, $headers);
    }

    /**
     * {@inheritdoc}
     */
    public function processData(PatternFileData $fileData, object $theme): array
    {
        $patternData = $fileData->getHeaders();

        return $this->collectionFactory->make($patternData)
            ->map(function ($value, $key) use ($theme) {
                if (in_array($key, PatternConstants::ARRAY_PROPERTIES, true)) {
                    return $value ? explode(',', $value) : null;
                }
                if (in_array($key, PatternConstants::INTEGER_PROPERTIES, true)) {
                    return $value ? (int) $value : null;
                }
                if (in_array($key, PatternConstants::BOOLEAN_PROPERTIES, true)) {
                    return $value ? in_array(strtolower($value), PatternConstants::TRUE_VALUES, true) : null;
                }
                if (in_array($key, PatternConstants::TRANSLATABLE_PROPERTIES, true)) {
                    if (! function_exists('translate_with_gettext_context') || ! method_exists($theme, 'get')) {
                        return $value;
                    }

                    $context = $key === 'title' ? 'Pattern title' : 'Pattern description';
                    $domain = $theme->get('TextDomain');

                    if (! is_string($domain)) {
                        return $value;
                    }

                    return \translate_with_gettext_context($value, $context, $domain);
                }

                return $value;
            })
            ->filter()
            ->all();
    }

    /**
     * {@inheritdoc}
     */
    public function getContent(string $file): ?string
    {
        $viewName = Str::replaceLast(PatternConstants::PATTERN_FILE_EXTENSION, '', Str::after($file, 'views/'));

        return View::exists($viewName) ? View::make($viewName)->render() : null;
    }
}
