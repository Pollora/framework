<?php

declare(strict_types=1);

// A Laravel validator for typed meta, without an application.

use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Pollora\Meta\Infrastructure\Services\LaravelMetaValidator;

function metaValidator(): LaravelMetaValidator
{
    $loader = new ArrayLoader;
    $loader->addMessages('en', 'validation', [
        'max' => ['numeric' => 'The :attribute field must not be greater than :max.'],
        'min' => ['numeric' => 'The :attribute field must be at least :min.'],
        'in' => 'The selected :attribute is invalid.',
        'email' => 'The :attribute field must be a valid email address.',
        'integer' => 'The :attribute field must be an integer.',
    ]);

    return new LaravelMetaValidator(new Factory(new Translator($loader, 'en')));
}
