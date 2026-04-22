<?php

declare(strict_types=1);

use Pollora\Plugin\Domain\Models\PluginModule;

describe('PluginModule', function (): void {
    beforeEach(function (): void {
        $this->pluginName = 'test-plugin';
        $this->pluginPath = '/path/to/plugins/test-plugin';
        $this->module = new PluginModule($this->pluginName, $this->pluginPath);
    });

    it('can be instantiated', function (): void {
        expect($this->module)->toBeInstanceOf(PluginModule::class);
        expect($this->module->getName())->toBe($this->pluginName);
        expect($this->module->getPath())->toBe($this->pluginPath);
    });

    it('has default disabled state', function (): void {
        expect($this->module->isEnabled())->toBeFalse();
        expect($this->module->isDisabled())->toBeTrue();
        expect($this->module->isActive())->toBeFalse();
    });

    it('can be enabled and disabled', function (): void {
        $this->module->enable();
        expect($this->module->isEnabled())->toBeTrue();
        expect($this->module->isDisabled())->toBeFalse();

        $this->module->disable();
        expect($this->module->isEnabled())->toBeFalse();
        expect($this->module->isDisabled())->toBeTrue();
    });

    it('can be activated and deactivated', function (): void {
        $this->module->activate();
        expect($this->module->isActive())->toBeTrue();

        $this->module->deactivate();
        expect($this->module->isActive())->toBeFalse();
    });

    it('returns correct plugin data', function (): void {
        $this->module->setHeaders([
            'Name' => 'Test Plugin',
            'Description' => 'A test plugin',
            'Version' => '1.0.0',
            'Author' => 'Test Author',
        ]);

        $data = $this->module->getPluginData();

        expect($data)->toHaveKey('Name');
        expect($data)->toHaveKey('Description');
        expect($data)->toHaveKey('Version');
        expect($data)->toHaveKey('Author');
        expect($data['Name'])->toBe('Test Plugin');
        expect($data['Description'])->toBe('A test plugin');
        expect($data['Version'])->toBe('1.0.0');
        expect($data['Author'])->toBe('Test Author');
    });

    it('returns correct main file path', function (): void {
        expect($this->module->getMainFile())->toBe($this->pluginPath.'/'.$this->pluginName.'.php');
    });

    it('returns default version', function (): void {
        expect($this->module->getVersion())->toBe('1.0.0');
    });

    it('returns custom version from headers', function (): void {
        $this->module->setHeaders(['Version' => '2.1.0']);
        expect($this->module->getVersion())->toBe('2.1.0');
    });

    it('returns empty author by default', function (): void {
        expect($this->module->getAuthor())->toBe('');
    });

    it('returns custom author from headers', function (): void {
        $this->module->setHeaders(['Author' => 'John Doe']);
        expect($this->module->getAuthor())->toBe('John Doe');
    });

    it('returns null plugin URI by default', function (): void {
        expect($this->module->getPluginUri())->toBeNull();
    });

    it('returns custom plugin URI from headers', function (): void {
        $this->module->setHeaders(['PluginURI' => 'https://example.com/plugin']);
        expect($this->module->getPluginUri())->toBe('https://example.com/plugin');
    });

    it('returns null author URI by default', function (): void {
        expect($this->module->getAuthorUri())->toBeNull();
    });

    it('returns custom author URI from headers', function (): void {
        $this->module->setHeaders(['AuthorURI' => 'https://example.com']);
        expect($this->module->getAuthorUri())->toBe('https://example.com');
    });

    it('is not network wide by default', function (): void {
        expect($this->module->isNetworkWide())->toBeFalse();
    });

    it('can be set as network wide', function (): void {
        $this->module->setHeaders(['Network' => true]);
        expect($this->module->isNetworkWide())->toBeTrue();
    });

    it('returns plugin name as text domain by default', function (): void {
        expect($this->module->getTextDomain())->toBe($this->pluginName);
    });

    it('returns custom text domain from headers', function (): void {
        $this->module->setHeaders(['TextDomain' => 'custom-text-domain']);
        expect($this->module->getTextDomain())->toBe('custom-text-domain');
    });

    it('returns default domain path', function (): void {
        expect($this->module->getDomainPath())->toBe('/languages');
    });

    it('returns custom domain path from headers', function (): void {
        $this->module->setHeaders(['DomainPath' => '/lang']);
        expect($this->module->getDomainPath())->toBe('/lang');
    });

    it('returns empty headers by default', function (): void {
        expect($this->module->getHeaders())->toBe([]);
    });

    it('stores and returns headers', function (): void {
        $headers = ['Name' => 'Test Plugin', 'Version' => '1.0.0', 'Author' => 'Test Author'];
        $this->module->setHeaders($headers);
        expect($this->module->getHeaders())->toBe($headers);
    });

    it('returns plugin slug', function (): void {
        expect($this->module->getSlug())->toBe($this->pluginName);
    });

    it('returns plugin basename', function (): void {
        expect($this->module->getBasename())->toBe($this->pluginName.'/'.$this->pluginName.'.php');
    });

    it('returns root namespace', function (): void {
        expect($this->module->getRootNamespace())->toBe('Plugin');
    });

    it('returns plugin namespace', function (): void {
        expect($this->module->getNamespace())->toBe('Plugin\\TestPlugin');
    });

    it('normalizes plugin name for namespace', function (): void {
        $plugin = new PluginModule('my-awesome-plugin', '/path');
        expect($plugin->getNamespace())->toBe('Plugin\\MyAwesomePlugin');
    });

    it('can set active status', function (): void {
        expect($this->module->isActive())->toBeFalse();

        $result = $this->module->setActive(true);
        expect($this->module->isActive())->toBeTrue();
        expect($result)->toBe($this->module);

        $this->module->setActive(false);
        expect($this->module->isActive())->toBeFalse();
    });

    it('can set enabled status', function (): void {
        expect($this->module->isEnabled())->toBeFalse();

        $result = $this->module->setEnabled(true);
        expect($this->module->isEnabled())->toBeTrue();
        expect($result)->toBe($this->module);

        $this->module->setEnabled(false);
        expect($this->module->isEnabled())->toBeFalse();
    });

    it('returns null for optional version fields', function (): void {
        expect($this->module->getRequiredWordPressVersion())->toBeNull();
        expect($this->module->getTestedWordPressVersion())->toBeNull();
        expect($this->module->getRequiredPhpVersion())->toBeNull();
    });

    it('returns custom version requirements from headers', function (): void {
        $this->module->setHeaders([
            'RequiresWP' => '5.0',
            'TestedUpTo' => '6.0',
            'RequiresPHP' => '8.0',
        ]);

        expect($this->module->getRequiredWordPressVersion())->toBe('5.0');
        expect($this->module->getTestedWordPressVersion())->toBe('6.0');
        expect($this->module->getRequiredPhpVersion())->toBe('8.0');
    });
});
