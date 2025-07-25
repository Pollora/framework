<?php

declare(strict_types=1);

namespace Tests\Unit\Option\Infrastructure\Repositories;

use PHPUnit\Framework\TestCase;
use Pollora\Option\Domain\Models\Option;
use Pollora\Option\Infrastructure\Repositories\WordPressOptionRepository;

final class WordPressOptionRepositoryTest extends TestCase
{
    private WordPressOptionRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        setupWordPressMocks();
        $this->repository = new WordPressOptionRepository;
    }

    public function test_get_returns_option_when_exists(): void
    {
        $key = 'test_key';
        $value = 'test_value';

        setWordPressFunction('get_option', function ($optionKey, $default) use ($key, $value) {
            return $optionKey === $key ? $value : $default;
        });

        $result = $this->repository->get($key);

        $this->assertInstanceOf(Option::class, $result);
        $this->assertEquals($key, $result->key);
        $this->assertEquals($value, $result->value);
    }

    public function test_get_returns_null_when_not_exists(): void
    {
        $key = 'non_existent_key';

        setWordPressFunction('get_option', function ($optionKey, $default) use ($key) {
            return $optionKey === $key ? null : $default;
        });

        $result = $this->repository->get($key);

        $this->assertNull($result);
    }

    public function test_store_returns_true(): void
    {
        $option = new Option('test_key', 'test_value', true);

        setWordPressFunction('add_option', function () {
            return true;
        });

        $result = $this->repository->store($option);

        $this->assertTrue($result);
    }

    public function test_update_returns_true(): void
    {
        $option = new Option('test_key', 'updated_value', false);

        setWordPressFunction('update_option', function () {
            return true;
        });

        $result = $this->repository->update($option);

        $this->assertTrue($result);
    }

    public function test_delete_returns_true(): void
    {
        $key = 'test_key';

        setWordPressFunction('delete_option', function () {
            return true;
        });

        $result = $this->repository->delete($key);

        $this->assertTrue($result);
    }

    public function test_exists_returns_true_when_option_has_value(): void
    {
        $key = 'existing_key';

        setWordPressFunction('get_option', function ($optionKey, $default) use ($key) {
            return $optionKey === $key ? 'some_value' : $default;
        });

        $result = $this->repository->exists($key);

        $this->assertTrue($result);
    }

    public function test_exists_returns_false_when_option_is_null(): void
    {
        $key = 'non_existent_key';

        setWordPressFunction('get_option', function ($optionKey, $default) use ($key) {
            return $optionKey === $key ? null : $default;
        });

        $result = $this->repository->exists($key);

        $this->assertFalse($result);
    }
}
