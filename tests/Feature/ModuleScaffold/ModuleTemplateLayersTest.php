<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Pollora\Modules\Infrastructure\Services\ModuleScaffolderService;
use Pollora\Modules\Infrastructure\Services\ModuleTemplate;

beforeEach(function (): void {
    $this->dir = sys_get_temp_dir().'/pollora-template-'.uniqid();
    File::ensureDirectoryExists($this->dir);
});

afterEach(function (): void {
    File::deleteDirectory($this->dir);
});

describe('ModuleTemplate layers', function (): void {
    it('adds the layers even when the module has no manifest to update', function (): void {
        mkdir($this->dir.'/Crm', 0777, true);
        $template = new ModuleTemplate(new ModuleScaffolderService);

        $template->addLayers($this->dir.'/Crm', ['database', 'tests'], ['%module_namespace%' => 'Modules\\Crm']);

        expect(is_dir($this->dir.'/Crm/database/migrations'))->toBeTrue()
            ->and(is_dir($this->dir.'/Crm/database/seeders'))->toBeTrue()
            ->and(is_dir($this->dir.'/Crm/database/factories'))->toBeTrue()
            ->and(is_dir($this->dir.'/Crm/tests/Unit'))->toBeTrue()
            ->and(file_exists($this->dir.'/Crm/composer.json'))->toBeFalse();
    });

    it('leaves a manifest that is not valid JSON untouched', function (): void {
        mkdir($this->dir.'/Crm', 0777, true);
        file_put_contents($this->dir.'/Crm/composer.json', '{ not json');
        file_put_contents($this->dir.'/Crm/module.json', '"scalar"');
        $template = new ModuleTemplate(new ModuleScaffolderService);

        $template->addLayers($this->dir.'/Crm', ['provider', 'tests'], ['%module_namespace%' => 'Modules\\Crm']);

        expect(file_get_contents($this->dir.'/Crm/composer.json'))->toBe('{ not json')
            ->and(file_get_contents($this->dir.'/Crm/module.json'))->toBe('"scalar"');
    });

    it('registers the provider and PSR-4 entries of the layers in the manifests', function (): void {
        mkdir($this->dir.'/Crm', 0777, true);
        file_put_contents($this->dir.'/Crm/composer.json', json_encode(['name' => 'x/crm']));
        file_put_contents($this->dir.'/Crm/module.json', json_encode(['name' => 'Crm', 'providers' => []]));
        $template = new ModuleTemplate(new ModuleScaffolderService);

        $template->addLayers($this->dir.'/Crm', ['provider', 'database', 'tests'], ['%module_namespace%' => 'Modules\\Crm']);
        $template->addLayers($this->dir.'/Crm', ['provider'], ['%module_namespace%' => 'Modules\\Crm']);

        $composer = json_decode((string) file_get_contents($this->dir.'/Crm/composer.json'), true);
        $manifest = json_decode((string) file_get_contents($this->dir.'/Crm/module.json'), true);

        expect($manifest['providers'])->toBe(['Modules\\Crm\\Providers\\CrmServiceProvider'])
            ->and($composer['autoload']['psr-4'])->toBe([
                'Modules\\Crm\\Database\\Factories\\' => 'database/factories/',
                'Modules\\Crm\\Database\\Seeders\\' => 'database/seeders/',
            ])
            ->and($composer['autoload-dev']['psr-4'])->toBe(['Modules\\Crm\\Tests\\' => 'tests/']);
    });
});
