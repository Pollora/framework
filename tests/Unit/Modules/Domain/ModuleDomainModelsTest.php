<?php

declare(strict_types=1);

use Pollora\Modules\Domain\Exceptions\ModuleException;
use Pollora\Modules\Domain\Models\AbstractModule;
use Pollora\Modules\Domain\Models\ModuleCollection;

function domainModule(string $name, bool $enabled = true, array $metadata = [], string $path = '/modules'): AbstractModule
{
    return new class($name, $path, $enabled, $metadata) extends AbstractModule
    {
        public function __construct(string $name, string $path, private bool $enabled, array $metadata)
        {
            parent::__construct($name, $path);
            $this->setMetadata($metadata);
        }

        public function getRootNamespace(): string
        {
            return 'Modules';
        }

        public function getNamespace(): string
        {
            return 'Modules\\'.$this->getStudlyName();
        }

        public function boot(): void {}

        public function register(): void {}

        public function isEnabled(): bool
        {
            return $this->enabled;
        }

        public function isDisabled(): bool
        {
            return ! $this->enabled;
        }

        public function enable(): void
        {
            $this->enabled = true;
        }

        public function disable(): void
        {
            $this->enabled = false;
        }

        public function exposedMetadata(): array
        {
            return $this->getMetadata();
        }
    };
}

describe('AbstractModule', function (): void {
    it('derives the lower case and studly spellings of its name', function (): void {
        $module = domainModule('blog-post_Archive');

        expect($module->getName())->toBe('blog-post_Archive')
            ->and($module->getLowerName())->toBe('blog-post_archive')
            ->and($module->getStudlyName())->toBe('BlogPostArchive')
            ->and((string) $module)->toBe('BlogPostArchive');
    });

    it('splits camel case names for kebab and snake', function (): void {
        $module = domainModule('BlogPost');

        expect($module->getKebabName())->toBe('blog-post')
            ->and($module->getSnakeName())->toBe('blog_post');
    });

    it('moves to a new path fluently', function (): void {
        $module = domainModule('Blog');

        expect($module->setPath('/elsewhere'))->toBe($module)
            ->and($module->getPath())->toBe('/elsewhere');
    });

    it('reads and writes metadata with dot notation and defaults', function (): void {
        $module = domainModule('Blog', true, ['version' => '1.0', 'author' => ['name' => 'Ada']]);

        expect($module->get('author.name'))->toBe('Ada')
            ->and($module->get('missing', 'fallback'))->toBe('fallback')
            ->and($module->set('author.email', 'ada@example.test'))->toBe($module)
            ->and($module->get('author.email'))->toBe('ada@example.test')
            ->and($module->exposedMetadata()['author'])->toBe(['name' => 'Ada', 'email' => 'ada@example.test']);
    });

    it('describes itself, with an empty string when it has no description', function (): void {
        expect(domainModule('Blog')->getDescription())->toBe('')
            ->and(domainModule('Blog', true, ['description' => 'A blog'])->getDescription())->toBe('A blog');
    });
});

describe('ModuleCollection', function (): void {
    beforeEach(function (): void {
        $this->a = domainModule('Alpha', true, ['priority' => 5, 'description' => 'first'], '/m/alpha');
        $this->b = domainModule('Beta', false, ['priority' => 1], '/m/beta');
        $this->c = domainModule('Gamma', true, ['priority' => 5], '/m/gamma');
        $this->d = domainModule('Delta', true, [], '/m/delta');
        $this->modules = new ModuleCollection([$this->a, $this->b, $this->c, $this->d]);
    });

    it('splits modules by their enabled state', function (): void {
        expect($this->modules->enabled()->map->getName()->values()->all())->toBe(['Alpha', 'Gamma', 'Delta'])
            ->and($this->modules->disabled()->map->getName()->values()->all())->toBe(['Beta'])
            ->and($this->modules->getByStatus(false))->toBeInstanceOf(ModuleCollection::class);
    });

    it('orders by priority ascending, treating a missing priority as 0 and keeping ties stable', function (): void {
        expect($this->modules->ordered()->map->getName()->values()->all())->toBe(['Delta', 'Beta', 'Alpha', 'Gamma']);
    });

    it('orders by priority descending', function (): void {
        $names = $this->modules->ordered('desc')->map->getName()->values()->all();

        expect($names)->toHaveCount(4)
            ->and(array_slice($names, 0, 2))->toEqualCanonicalizing(['Alpha', 'Gamma'])
            ->and(array_slice($names, 2))->toBe(['Beta', 'Delta']);
    });

    it('finds a module by name regardless of case', function (): void {
        expect($this->modules->findByName('gAMMA'))->toBe($this->c)
            ->and($this->modules->findByName('Nope'))->toBeNull()
            ->and($this->modules->hasByName('beta'))->toBeTrue()
            ->and($this->modules->hasByName('nope'))->toBeFalse();
    });

    it('exports modules keyed by lower name for a theme', function (): void {
        expect($this->modules->toThemeArray()['alpha'])->toBe([
            'name' => 'Alpha',
            'description' => 'first',
            'path' => '/m/alpha',
            'enabled' => true,
        ])->and(array_keys($this->modules->toThemeArray()))->toBe(['alpha', 'beta', 'gamma', 'delta']);
    });
});

describe('ModuleException', function (): void {
    it('names the module in every state message', function (): void {
        expect(ModuleException::notFound('Blog')->getMessage())->toBe('Module [Blog] not found.')
            ->and(ModuleException::alreadyEnabled('Blog')->getMessage())->toBe('Module [Blog] is already enabled.')
            ->and(ModuleException::alreadyDisabled('Blog')->getMessage())->toBe('Module [Blog] is already disabled.');
    });

    it('adds the reason to a refusal only when one is given', function (): void {
        expect(ModuleException::cannotEnable('Blog')->getMessage())->toBe('Cannot enable module [Blog]')
            ->and(ModuleException::cannotEnable('Blog', 'missing dependency')->getMessage())->toBe('Cannot enable module [Blog]: missing dependency')
            ->and(ModuleException::cannotDisable('Blog')->getMessage())->toBe('Cannot disable module [Blog]')
            ->and(ModuleException::cannotDisable('Blog', 'in use')->getMessage())->toBe('Cannot disable module [Blog]: in use');
    });

    it('tells what to check for each download failure', function (): void {
        expect(ModuleException::repositoryUnreachable('a/b')->getMessage())->toContain('a/b', 'GitHub is reachable')
            ->and(ModuleException::archiveNotDownloaded('https://x.test/a.zip')->getMessage())->toContain('https://x.test/a.zip', 'release exists')
            ->and(ModuleException::archiveNotReadable('/tmp/a.zip')->getMessage())->toContain('/tmp/a.zip', 'try again')
            ->and(ModuleException::archiveEmpty('/tmp/x')->getMessage())->toContain('/tmp/x', 'no module folder');
    });
});
