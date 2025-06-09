<?php

declare(strict_types=1);

namespace Pollora\BlockPattern\Infrastructure\Adapters;

use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Pollora\BlockPattern\Domain\Contracts\PatternDataExtractorInterface;
use Pollora\BlockPattern\Domain\Models\PatternFileData;
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
     * File extension for pattern files.
     */
    private const PATTERN_FILE_EXTENSION = '.blade.php';

    /**
     * Default headers to extract from pattern files.
     *
     * @var array<string, string>
     */
    protected array $defaultHeaders = [
        'title' => 'Title',
        'slug' => 'Slug',
        'description' => 'Description',
        'viewportWidth' => 'Viewport Width',
        'categories' => 'Categories',
        'keywords' => 'Keywords',
        'blockTypes' => 'Block Types',
        'postTypes' => 'Post Types',
        'inserter' => 'Inserter',
    ];

    /**
     * Create a new WordPress pattern data extractor instance.
     */
    public function __construct(
        private CollectionFactoryInterface $collectionFactory
    ) {}

    /**
     * {@inheritdoc}
     */
    public function extractFromFile(string $file): PatternFileData
    {
        $headers = [];

        if (function_exists('get_file_data')) {
            $headers = \get_file_data($file, $this->defaultHeaders);
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
                $arrayProperties = ['categories', 'keywords', 'blockTypes', 'postTypes'];
                if (in_array($key, $arrayProperties, true)) {
                    return $value ? explode(',', $value) : null;
                }
                if ($key === 'viewportWidth') {
                    return $value ? (int) $value : null;
                }
                if ($key === 'inserter') {
                    return $value ? in_array(strtolower($value), ['yes', 'true']) : null;
                }
                if (in_array($key, ['title', 'description'])) {
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
        $viewName = Str::replaceLast(self::PATTERN_FILE_EXTENSION, '', Str::after($file, 'views/'));

        return View::exists($viewName) ? View::make($viewName)->render() : null;
    }
}
