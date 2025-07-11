<?php

declare(strict_types=1);

namespace Tests\Unit\Plugin;

use PHPUnit\Framework\TestCase;
use Pollora\Plugin\Domain\Models\PluginModule;

/**
 * Tests for PluginModule class.
 */
class PluginModuleTest extends TestCase
{
    private PluginModule $pluginModule;

    private string $pluginName;

    private string $pluginPath;

    protected function setUp(): void
    {
        $this->pluginName = 'test-plugin';
        $this->pluginPath = '/path/to/plugins/test-plugin';
        $this->pluginModule = new PluginModule($this->pluginName, $this->pluginPath);
    }

    public function test_it_can_be_instantiated(): void
    {
        $this->assertInstanceOf(PluginModule::class, $this->pluginModule);
        $this->assertEquals($this->pluginName, $this->pluginModule->getName());
        $this->assertEquals($this->pluginPath, $this->pluginModule->getPath());
    }

    public function test_it_has_default_disabled_state(): void
    {
        $this->assertFalse($this->pluginModule->isEnabled());
        $this->assertTrue($this->pluginModule->isDisabled());
        $this->assertFalse($this->pluginModule->isActive());
    }

    public function test_it_can_be_enabled_and_disabled(): void
    {
        $this->pluginModule->enable();
        $this->assertTrue($this->pluginModule->isEnabled());
        $this->assertFalse($this->pluginModule->isDisabled());

        $this->pluginModule->disable();
        $this->assertFalse($this->pluginModule->isEnabled());
        $this->assertTrue($this->pluginModule->isDisabled());
    }

    public function test_it_can_be_activated_and_deactivated(): void
    {
        $this->pluginModule->activate();
        $this->assertTrue($this->pluginModule->isActive());

        $this->pluginModule->deactivate();
        $this->assertFalse($this->pluginModule->isActive());
    }

    public function test_it_returns_correct_plugin_data(): void
    {
        $this->pluginModule->setHeaders([
            'Name' => 'Test Plugin',
            'Description' => 'A test plugin',
            'Version' => '1.0.0',
            'Author' => 'Test Author',
        ]);

        $pluginData = $this->pluginModule->getPluginData();

        $this->assertArrayHasKey('Name', $pluginData);
        $this->assertArrayHasKey('Description', $pluginData);
        $this->assertArrayHasKey('Version', $pluginData);
        $this->assertArrayHasKey('Author', $pluginData);
        $this->assertEquals('Test Plugin', $pluginData['Name']);
        $this->assertEquals('A test plugin', $pluginData['Description']);
        $this->assertEquals('1.0.0', $pluginData['Version']);
        $this->assertEquals('Test Author', $pluginData['Author']);
    }

    public function test_it_returns_correct_main_file_path(): void
    {
        $expectedPath = $this->pluginPath.'/'.$this->pluginName.'.php';
        $this->assertEquals($expectedPath, $this->pluginModule->getMainFile());
    }

    public function test_it_returns_default_version(): void
    {
        $this->assertEquals('1.0.0', $this->pluginModule->getVersion());
    }

    public function test_it_returns_custom_version_from_headers(): void
    {
        $this->pluginModule->setHeaders(['Version' => '2.1.0']);
        $this->assertEquals('2.1.0', $this->pluginModule->getVersion());
    }

    public function test_it_returns_empty_author_by_default(): void
    {
        $this->assertEquals('', $this->pluginModule->getAuthor());
    }

    public function test_it_returns_custom_author_from_headers(): void
    {
        $this->pluginModule->setHeaders(['Author' => 'John Doe']);
        $this->assertEquals('John Doe', $this->pluginModule->getAuthor());
    }

    public function test_it_returns_null_plugin_uri_by_default(): void
    {
        $this->assertNull($this->pluginModule->getPluginUri());
    }

    public function test_it_returns_custom_plugin_uri_from_headers(): void
    {
        $uri = 'https://example.com/plugin';
        $this->pluginModule->setHeaders(['PluginURI' => $uri]);
        $this->assertEquals($uri, $this->pluginModule->getPluginUri());
    }

    public function test_it_returns_null_author_uri_by_default(): void
    {
        $this->assertNull($this->pluginModule->getAuthorUri());
    }

    public function test_it_returns_custom_author_uri_from_headers(): void
    {
        $uri = 'https://example.com';
        $this->pluginModule->setHeaders(['AuthorURI' => $uri]);
        $this->assertEquals($uri, $this->pluginModule->getAuthorUri());
    }

    public function test_it_is_not_network_wide_by_default(): void
    {
        $this->assertFalse($this->pluginModule->isNetworkWide());
    }

    public function test_it_can_be_set_as_network_wide(): void
    {
        $this->pluginModule->setHeaders(['Network' => true]);
        $this->assertTrue($this->pluginModule->isNetworkWide());
    }

    public function test_it_returns_plugin_name_as_text_domain_by_default(): void
    {
        $this->assertEquals($this->pluginName, $this->pluginModule->getTextDomain());
    }

    public function test_it_returns_custom_text_domain_from_headers(): void
    {
        $textDomain = 'custom-text-domain';
        $this->pluginModule->setHeaders(['TextDomain' => $textDomain]);
        $this->assertEquals($textDomain, $this->pluginModule->getTextDomain());
    }

    public function test_it_returns_default_domain_path(): void
    {
        $this->assertEquals('/languages', $this->pluginModule->getDomainPath());
    }

    public function test_it_returns_custom_domain_path_from_headers(): void
    {
        $domainPath = '/lang';
        $this->pluginModule->setHeaders(['DomainPath' => $domainPath]);
        $this->assertEquals($domainPath, $this->pluginModule->getDomainPath());
    }

    public function test_it_returns_empty_headers_by_default(): void
    {
        $this->assertEquals([], $this->pluginModule->getHeaders());
    }

    public function test_it_stores_and_returns_headers(): void
    {
        $headers = [
            'Name' => 'Test Plugin',
            'Version' => '1.0.0',
            'Author' => 'Test Author',
        ];

        $this->pluginModule->setHeaders($headers);
        $this->assertEquals($headers, $this->pluginModule->getHeaders());
    }

    public function test_it_returns_plugin_slug(): void
    {
        $this->assertEquals($this->pluginName, $this->pluginModule->getSlug());
    }

    public function test_it_returns_plugin_basename(): void
    {
        $expectedBasename = $this->pluginName.'/'.$this->pluginName.'.php';
        $this->assertEquals($expectedBasename, $this->pluginModule->getBasename());
    }

    public function test_it_returns_root_namespace(): void
    {
        $this->assertEquals('Plugin', $this->pluginModule->getRootNamespace());
    }

    public function test_it_returns_plugin_namespace(): void
    {
        $expectedNamespace = 'Plugin\\TestPlugin';
        $this->assertEquals($expectedNamespace, $this->pluginModule->getNamespace());
    }

    public function test_it_normalizes_plugin_name_for_namespace(): void
    {
        $plugin = new PluginModule('my-awesome-plugin', '/path');
        $expectedNamespace = 'Plugin\\MyAwesomePlugin';
        $this->assertEquals($expectedNamespace, $plugin->getNamespace());
    }

    public function test_it_can_set_active_status(): void
    {
        $this->assertFalse($this->pluginModule->isActive());

        $result = $this->pluginModule->setActive(true);
        $this->assertTrue($this->pluginModule->isActive());
        $this->assertSame($this->pluginModule, $result);

        $this->pluginModule->setActive(false);
        $this->assertFalse($this->pluginModule->isActive());
    }

    public function test_it_can_set_enabled_status(): void
    {
        $this->assertFalse($this->pluginModule->isEnabled());

        $result = $this->pluginModule->setEnabled(true);
        $this->assertTrue($this->pluginModule->isEnabled());
        $this->assertSame($this->pluginModule, $result);

        $this->pluginModule->setEnabled(false);
        $this->assertFalse($this->pluginModule->isEnabled());
    }

    public function test_it_returns_null_for_optional_version_fields(): void
    {
        $this->assertNull($this->pluginModule->getRequiredWordPressVersion());
        $this->assertNull($this->pluginModule->getTestedWordPressVersion());
        $this->assertNull($this->pluginModule->getRequiredPhpVersion());
    }

    public function test_it_returns_custom_version_requirements_from_headers(): void
    {
        $this->pluginModule->setHeaders([
            'RequiresWP' => '5.0',
            'TestedUpTo' => '6.0',
            'RequiresPHP' => '8.0',
        ]);

        $this->assertEquals('5.0', $this->pluginModule->getRequiredWordPressVersion());
        $this->assertEquals('6.0', $this->pluginModule->getTestedWordPressVersion());
        $this->assertEquals('8.0', $this->pluginModule->getRequiredPhpVersion());
    }
}
