<?php

declare(strict_types=1);

use Pollora\BlockPattern\Infrastructure\Helpers\PatternDataProcessor;

require_once __DIR__.'/../../helpers.php';

describe('PatternDataProcessor', function () {

    it('extracts data from pattern file', function () {
        $processor = new PatternDataProcessor;
        // Using the already defined stub for get_file_data
        $data = $processor->getPatternData('dummy-path');
        expect($data['title'])->toBe('Title')
            ->and($data['slug'])->toBe('slug-demo');
    });

    it('processes array fields and viewportWidth', function () {
        $processor = new PatternDataProcessor;
        $patternData = [
            'categories' => 'news,updates',
            'keywords' => 'foo,bar',
            'viewportWidth' => '1200',
        ];
        $theme = Mockery::mock('WP_Theme');
        $theme->shouldReceive('get')->with('TextDomain')->andReturn('test-domain');
        $result = $processor->process($patternData, $theme);
        expect($result['categories'])->toBe(['news', 'updates'])
            ->and($result['keywords'])->toBe(['foo', 'bar'])
            ->and($result['viewportWidth'])->toBe(1200);
    });

    it('filters empty values', function () {
        $processor = new PatternDataProcessor;
        $patternData = [
            'categories' => '',
            'description' => null,
        ];
        $theme = Mockery::mock('WP_Theme');
        $theme->shouldReceive('get')->with('TextDomain')->andReturn('test-domain');
        $result = $processor->process($patternData, $theme);
        expect($result)->not->toHaveKey('categories')
            ->and($result)->not->toHaveKey('description');
    });
});
