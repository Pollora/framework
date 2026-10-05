<?php

declare(strict_types=1);

namespace Tests\Unit\Meta\Fixtures;

use Pollora\Attributes\Meta;
use Pollora\Attributes\PostType;
use Pollora\Attributes\Taxonomy;

#[PostType('invalid-union')]
class InvalidUnion
{
    #[Meta]
    public int|string $value = 0;
}

#[PostType('invalid-array')]
class InvalidArray
{
    #[Meta]
    public array $values = [];
}

#[PostType('invalid-pure-enum')]
class InvalidPureEnum
{
    #[Meta]
    public ?Mood $mood = null;
}

#[PostType('invalid-no-default')]
class InvalidNoDefault
{
    #[Meta]
    public int $count;
}

#[PostType('invalid-protected')]
class InvalidProtectedInRest
{
    #[Meta(key: '_secret', showInRest: true)]
    public string $secret = '';
}

#[Taxonomy('invalid-revisions')]
class InvalidTermRevisions
{
    #[Meta(revisions: true)]
    public string $note = '';
}

#[PostType('invalid-duplicate')]
class InvalidDuplicateKey
{
    #[Meta(key: 'shared')]
    public string $first = '';

    #[Meta(key: 'shared')]
    public string $second = '';
}

#[PostType('invalid-untyped')]
class InvalidUntyped
{
    #[Meta]
    public $value = '';
}
