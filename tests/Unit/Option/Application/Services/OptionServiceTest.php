<?php

declare(strict_types=1);

use Pollora\Option\Application\Services\OptionService;
use Pollora\Option\Domain\Contracts\OptionRepositoryInterface;
use Pollora\Option\Domain\Models\Option;
use Pollora\Option\Domain\Services\OptionValidationService;

describe('OptionService', function (): void {
    beforeEach(function (): void {
        $this->repository = Mockery::mock(OptionRepositoryInterface::class);
        $this->validator = new OptionValidationService;
        $this->service = new OptionService($this->repository, $this->validator);
    });

    it('returns option value when exists', function (): void {
        $this->repository->shouldReceive('get')->with('test_key')->once()->andReturn(new Option('test_key', 'test_value'));

        expect($this->service->get('test_key'))->toBe('test_value');
    });

    it('returns default when option not exists', function (): void {
        $this->repository->shouldReceive('get')->with('test_key')->once()->andReturn(null);

        expect($this->service->get('test_key', 'default_value'))->toBe('default_value');
    });

    it('returns null as default when no default provided', function (): void {
        $this->repository->shouldReceive('get')->with('test_key')->once()->andReturn(null);

        expect($this->service->get('test_key'))->toBeNull();
    });

    it('stores new option when not exists', function (): void {
        $this->repository->shouldReceive('exists')->with('test_key')->once()->andReturn(false);
        $this->repository->shouldReceive('store')->once()->andReturn(true);

        expect($this->service->set('test_key', 'test_value'))->toBeTrue();
    });

    it('updates existing option when exists', function (): void {
        $this->repository->shouldReceive('exists')->with('test_key')->once()->andReturn(true);
        $this->repository->shouldReceive('update')->once()->andReturn(true);

        expect($this->service->set('test_key', 'test_value'))->toBeTrue();
    });

    it('update calls repository update', function (): void {
        $this->repository->shouldReceive('update')->once()->andReturn(true);

        expect($this->service->update('test_key', 'test_value'))->toBeTrue();
    });

    it('delete calls repository delete', function (): void {
        $this->repository->shouldReceive('delete')->with('test_key')->once()->andReturn(true);

        expect($this->service->delete('test_key'))->toBeTrue();
    });

    it('exists calls repository exists', function (): void {
        $this->repository->shouldReceive('exists')->with('test_key')->once()->andReturn(true);

        expect($this->service->exists('test_key'))->toBeTrue();
    });
});
