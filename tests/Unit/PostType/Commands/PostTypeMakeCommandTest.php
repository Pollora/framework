<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Mockery as m;
use Pollora\PostType\UI\Console\PostTypeMakeCommand;

beforeEach(function () {
    // Create a mock filesystem
    $this->files = m::mock(Filesystem::class);

    // Create the command with the mock filesystem
    $this->command = new PostTypeMakeCommand($this->files);
});

afterEach(function () {
    m::close();
});

test('PostTypeMakeCommand generates correct slug from class name', function () {
    // Test the protected method via reflection
    $reflectionMethod = new ReflectionMethod(PostTypeMakeCommand::class, 'getSlugFromClassName');
    $reflectionMethod->setAccessible(true);

    $result = $reflectionMethod->invoke($this->command, 'EventRegistration');

    expect($result)->toBe('event-registration');
});

test('PostTypeMakeCommand generates correct singular name from class name', function () {
    // Test the protected method via reflection
    $reflectionMethod = new ReflectionMethod(PostTypeMakeCommand::class, 'getNameFromClassName');
    $reflectionMethod->setAccessible(true);

    $result = $reflectionMethod->invoke($this->command, 'EventRegistration');

    expect($result)->toBe('Event registration');
});

test('PostTypeMakeCommand generates correct plural name from class name', function () {
    // Test the protected method via reflection
    $reflectionMethod = new ReflectionMethod(PostTypeMakeCommand::class, 'getPluralNameFromClassName');
    $reflectionMethod->setAccessible(true);

    $result = $reflectionMethod->invoke($this->command, 'Event');

    expect($result)->toBe('Events');

    $result = $reflectionMethod->invoke($this->command, 'Category');

    expect($result)->toBe('Categories');
});
