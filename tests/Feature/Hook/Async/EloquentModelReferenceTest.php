<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Pollora\Hook\Infrastructure\Async\EloquentModelReference;

#[Unguarded]
#[Table(name: 'async_reference_orders')]
#[WithoutTimestamps]
final class AsyncReferenceOrder extends Model {}

beforeEach(function (): void {
    config(['database.default' => 'testing', 'database.connections.testing' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
    Schema::create('async_reference_orders', function (Blueprint $table): void {
        $table->id();
        $table->string('status');
    });

    $this->reference = new EloquentModelReference;
});

it('carries saved models only', function (): void {
    expect($this->reference->name())->toBe('eloquent')
        ->and($this->reference->supports(AsyncReferenceOrder::create(['status' => 'paid'])))->toBeTrue()
        ->and($this->reference->supports(new AsyncReferenceOrder(['status' => 'draft'])))->toBeFalse()
        ->and($this->reference->supports(new stdClass))->toBeFalse();
});

it('identifies a model by class and key, with its connection when it has one', function (): void {
    $order = (new AsyncReferenceOrder)->forceFill(['id' => 5]);
    $order->exists = true;

    expect($this->reference->reference($order))->toBe(['class' => AsyncReferenceOrder::class, 'key' => 5]);

    $order->setConnection('testing');

    expect($this->reference->reference($order))->toBe(['class' => AsyncReferenceOrder::class, 'key' => 5, 'connection' => 'testing']);
});

it('reloads the model in its current state, on its connection', function (?string $connection): void {
    $order = AsyncReferenceOrder::create(['status' => 'paid']);
    $identifier = $this->reference->reference($connection === null ? $order : $order->setConnection($connection));
    $order->update(['status' => 'refunded']);

    $reloaded = $this->reference->resolve($identifier);

    expect($reloaded)->toBeInstanceOf(AsyncReferenceOrder::class)
        ->and($reloaded->status)->toBe('refunded');
})->with(['default connection' => [null], 'named connection' => ['testing']]);

it('returns null for a deleted model or a class that is not a model', function (array $identifier): void {
    expect($this->reference->resolve($identifier))->toBeNull();
})->with([
    'deleted' => [['class' => AsyncReferenceOrder::class, 'key' => 999]],
    'not a model' => [['class' => stdClass::class, 'key' => 1]],
    'missing class' => [['key' => 1]],
]);
