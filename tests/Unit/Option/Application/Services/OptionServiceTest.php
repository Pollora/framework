<?php

declare(strict_types=1);

namespace Tests\Unit\Option\Application\Services;

use Mockery;
use PHPUnit\Framework\TestCase;
use Pollora\Option\Application\Services\OptionService;
use Pollora\Option\Domain\Contracts\OptionRepositoryInterface;
use Pollora\Option\Domain\Models\Option;
use Pollora\Option\Domain\Services\OptionValidationService;

final class OptionServiceTest extends TestCase
{
    private OptionService $service;

    private OptionRepositoryInterface $repository;

    private OptionValidationService $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(OptionRepositoryInterface::class);
        $this->validator = new OptionValidationService;
        $this->service = new OptionService($this->repository, $this->validator);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_get_returns_option_value_when_exists(): void
    {
        $key = 'test_key';
        $value = 'test_value';
        $option = new Option($key, $value);

        $this->repository->shouldReceive('get')->with($key)->once()->andReturn($option);

        $result = $this->service->get($key);

        $this->assertEquals($value, $result);
    }

    public function test_get_returns_default_when_option_not_exists(): void
    {
        $key = 'test_key';
        $default = 'default_value';

        $this->repository->shouldReceive('get')->with($key)->once()->andReturn(null);

        $result = $this->service->get($key, $default);

        $this->assertEquals($default, $result);
    }

    public function test_get_returns_null_as_default_when_no_default_provided(): void
    {
        $key = 'test_key';

        $this->repository->shouldReceive('get')->with($key)->once()->andReturn(null);

        $result = $this->service->get($key);

        $this->assertNull($result);
    }

    public function test_set_stores_new_option_when_not_exists(): void
    {
        $key = 'test_key';
        $value = 'test_value';

        $this->repository->shouldReceive('exists')->with($key)->once()->andReturn(false);
        $this->repository->shouldReceive('store')->once()->andReturn(true);

        $result = $this->service->set($key, $value);

        $this->assertTrue($result);
    }

    public function test_set_updates_existing_option_when_exists(): void
    {
        $key = 'test_key';
        $value = 'test_value';

        $this->repository->shouldReceive('exists')->with($key)->once()->andReturn(true);
        $this->repository->shouldReceive('update')->once()->andReturn(true);

        $result = $this->service->set($key, $value);

        $this->assertTrue($result);
    }

    public function test_update_calls_repository_update(): void
    {
        $key = 'test_key';
        $value = 'test_value';

        $this->repository->shouldReceive('update')->once()->andReturn(true);

        $result = $this->service->update($key, $value);

        $this->assertTrue($result);
    }

    public function test_delete_calls_repository_delete(): void
    {
        $key = 'test_key';

        $this->repository->shouldReceive('delete')->with($key)->once()->andReturn(true);

        $result = $this->service->delete($key);

        $this->assertTrue($result);
    }

    public function test_exists_calls_repository_exists(): void
    {
        $key = 'test_key';

        $this->repository->shouldReceive('exists')->with($key)->once()->andReturn(true);

        $result = $this->service->exists($key);

        $this->assertTrue($result);
    }
}
