<?php

declare(strict_types=1);

use Illuminate\Contracts\View\Factory as ViewFactory;
use Mockery as m;
use Pollora\View\Application\Services\TemplateHierarchyService;
use Pollora\View\Application\UseCases\RegisterTemplateHierarchyFiltersUseCase;
use Pollora\View\Application\UseCases\ResolveBladeTemplateUseCase;
use Pollora\View\Domain\Contracts\TemplateFinderInterface;
use Pollora\View\Infrastructure\Services\TemplateMarker;

describe('ResolveBladeTemplateUseCase', function (): void {
    beforeEach(function (): void {
        $this->dir = sys_get_temp_dir().'/pollora-resolve-'.bin2hex(random_bytes(6));
        mkdir($this->dir);
        touch($this->dir.'/page.blade.php');
        $this->path = $this->dir.'/page.blade.php';
    });

    afterEach(function (): void {
        @unlink($this->path);
        @rmdir($this->dir);
    });

    it('hands back the template path unchanged when the file does not exist', function (): void {
        $finder = m::mock(TemplateFinderInterface::class);
        $finder->shouldNotReceive('getViewNameFromPath');

        $useCase = new ResolveBladeTemplateUseCase($finder, m::mock(ViewFactory::class));

        expect($useCase->execute('/no/such/page.blade.php'))->toBe('/no/such/page.blade.php');
    });

    it('hands back the path unchanged when no view name can be derived from it', function (): void {
        $finder = m::mock(TemplateFinderInterface::class);
        $finder->shouldReceive('getViewNameFromPath')->once()->with(realpath($this->path))->andReturnNull();
        $views = m::mock(ViewFactory::class);
        $views->shouldNotReceive('exists');

        expect((new ResolveBladeTemplateUseCase($finder, $views))->execute($this->path))->toBe($this->path);
    });

    it('asks the view factory about the trimmed view name and keeps the original path either way', function (): void {
        $finder = m::mock(TemplateFinderInterface::class);
        $finder->shouldReceive('getViewNameFromPath')->andReturn('.pages.single/');
        $asked = [];
        $views = m::mock(ViewFactory::class);
        $views->shouldReceive('exists')->andReturnUsing(function (string $name) use (&$asked): bool {
            $asked[] = $name;

            return count($asked) === 1;
        });
        $useCase = new ResolveBladeTemplateUseCase($finder, $views);

        expect($useCase->execute($this->path))->toBe($this->path)
            ->and($useCase->execute($this->path))->toBe($this->path)
            ->and($asked)->toBe(['pages.single', 'pages.single']);
    });
});

describe('TemplateHierarchyService', function (): void {
    it('registers the hierarchy filters when initialized', function (): void {
        $filters = m::mock(RegisterTemplateHierarchyFiltersUseCase::class);
        $calls = 0;
        $filters->shouldReceive('execute')->andReturnUsing(function () use (&$calls): void {
            $calls++;
        });

        (new TemplateHierarchyService($filters))->initialize();

        expect($calls)->toBe(1);
    });
});

describe('TemplateMarker naming', function (): void {
    it('keeps a template name that has no known extension', function (): void {
        expect((new TemplateMarker)->name('/themes/x/README'))->toBe('README')
            ->and((new TemplateMarker)->name('/themes/x/notes.txt'))->toBe('notes.txt');
    });
});
