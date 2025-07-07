<?php

declare(strict_types=1);

namespace Tests\Unit\Plugin;

use PHPUnit\Framework\TestCase;
use Pollora\Plugin\Domain\Models\PluginMetadata;

/**
 * Tests for PluginMetadata class.
 */
class PluginMetadataTest extends TestCase
{
    private PluginMetadata $pluginMetadata;
    private string $pluginName;
    private string $basePath;

    protected function setUp(): void
    {
        $this->pluginName = 'test-plugin';
        $this->basePath = '/path/to/plugins';
        $this->pluginMetadata = new PluginMetadata($this->pluginName, $this->basePath);
    }

    public function test_it_can_be_instantiated_with_name_and_base_path(): void
    {
        $this->assertInstanceOf(PluginMetadata::class, $this->pluginMetadata);
        $this->assertEquals($this->pluginName, $this->pluginMetadata->getName());
    }

    public function test_it_returns_correct_base_path(): void
    {
        $expectedPath = $this->basePath.'/'.$this->pluginName;
        $this->assertEquals($expectedPath, $this->pluginMetadata->getBasePath());
    }

    public function test_it_returns_correct_main_file_path(): void
    {
        $expectedPath = $this->basePath.'/'.$this->pluginName.'/'.$this->pluginName.'.php';
        $this->assertEquals($expectedPath, $this->pluginMetadata->getMainFilePath());
    }

    public function test_it_returns_correct_config_path(): void
    {
        $expectedPath = $this->basePath.'/'.$this->pluginName.'/plugin.json';
        $this->assertEquals($expectedPath, $this->pluginMetadata->getConfigPath());
    }

    public function test_it_returns_correct_language_path(): void
    {
        $expectedPath = $this->basePath.'/'.$this->pluginName.'/languages';
        $this->assertEquals($expectedPath, $this->pluginMetadata->getLanguagePath());
    }

    public function test_it_returns_correct_plugin_namespace(): void
    {
        $expectedNamespace = 'TestPlugin';
        $this->assertEquals($expectedNamespace, $this->pluginMetadata->getPluginNamespace());
    }

    public function test_it_handles_complex_plugin_names(): void
    {
        $complexPlugin = new PluginMetadata('my-awesome-plugin', $this->basePath);
        $expectedNamespace = 'MyAwesomePlugin';
        $this->assertEquals($expectedNamespace, $complexPlugin->getPluginNamespace());
    }

    public function test_it_returns_correct_plugin_app_dir(): void
    {
        $expectedPath = $this->basePath.'/'.$this->pluginName.'/app';
        $this->assertEquals($expectedPath, $this->pluginMetadata->getPluginAppDir());
    }

    public function test_it_returns_correct_plugin_app_dir_with_subdirectory(): void
    {
        $subDirectory = 'Providers';
        $expectedPath = $this->basePath.'/'.$this->pluginName.'/app/'.$subDirectory;
        $this->assertEquals($expectedPath, $this->pluginMetadata->getPluginAppDir($subDirectory));
    }

    public function test_it_returns_correct_plugin_app_file(): void
    {
        $fileName = 'ServiceProvider.php';
        $expectedPath = $this->basePath.'/'.$this->pluginName.'/app/'.$fileName;
        $this->assertEquals($expectedPath, $this->pluginMetadata->getPluginAppFile($fileName));
    }

    public function test_it_returns_empty_config_initially(): void
    {
        $this->assertEquals([], $this->pluginMetadata->getConfig());
    }

    public function test_it_returns_correct_slug(): void
    {
        $this->assertEquals('test-plugin', $this->pluginMetadata->getSlug());
    }

    public function test_it_returns_correct_basename(): void
    {
        $expectedBasename = $this->pluginName.'/'.$this->pluginName.'.php';
        $this->assertEquals($expectedBasename, $this->pluginMetadata->getBasename());
    }

    public function test_it_handles_plugin_name_with_spaces(): void
    {
        $plugin = new PluginMetadata('My Plugin Name', $this->basePath);
        $this->assertEquals('my-plugin-name', $plugin->getSlug());
    }

    public function test_it_handles_plugin_name_with_underscores(): void
    {
        $plugin = new PluginMetadata('my_plugin_name', $this->basePath);
        $this->assertEquals('my-plugin-name', $plugin->getSlug());
    }

    public function test_it_returns_correct_views_path(): void
    {
        $expectedPath = $this->basePath.'/'.$this->pluginName.'/views';
        $this->assertEquals($expectedPath, $this->pluginMetadata->getViewsPath());
    }

    public function test_it_returns_correct_assets_path(): void
    {
        $expectedPath = $this->basePath.'/'.$this->pluginName.'/assets';
        $this->assertEquals($expectedPath, $this->pluginMetadata->getAssetsPath());
    }

    public function test_it_returns_correct_routes_path(): void
    {
        $expectedPath = $this->basePath.'/'.$this->pluginName.'/routes';
        $this->assertEquals($expectedPath, $this->pluginMetadata->getRoutesPath());
    }

    public function test_it_returns_correct_config_dir(): void
    {
        $expectedPath = $this->basePath.'/'.$this->pluginName.'/config';
        $this->assertEquals($expectedPath, $this->pluginMetadata->getConfigDir());
    }

    public function test_it_returns_correct_database_path(): void
    {
        $expectedPath = $this->basePath.'/'.$this->pluginName.'/database';
        $this->assertEquals($expectedPath, $this->pluginMetadata->getDatabasePath());
    }

    public function test_it_returns_correct_tests_path(): void
    {
        $expectedPath = $this->basePath.'/'.$this->pluginName.'/tests';
        $this->assertEquals($expectedPath, $this->pluginMetadata->getTestsPath());
    }

    public function test_it_returns_path_for_item_with_array(): void
    {
        $pathParts = ['config', 'app.php'];
        $expectedPath = $this->basePath.'/'.$this->pluginName.'/config/app.php';
        $this->assertEquals($expectedPath, $this->pluginMetadata->getPathForItem($pathParts));
    }

    public function test_it_returns_path_for_item_with_string(): void
    {
        $pathPart = 'config';
        $expectedPath = $this->basePath.'/'.$this->pluginName.'/config';
        $this->assertEquals($expectedPath, $this->pluginMetadata->getPathForItem($pathPart));
    }

    public function test_it_returns_path_for_item_with_null(): void
    {
        $expectedPath = $this->basePath.'/'.$this->pluginName.'/';
        $this->assertEquals($expectedPath, $this->pluginMetadata->getPathForItem(null));
    }
}