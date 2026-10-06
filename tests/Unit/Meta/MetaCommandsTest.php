<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Illuminate\Console\Command;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Pollora\Meta\Application\Services\MetaAuditor;
use Pollora\Meta\Application\Services\MetaSchemaBuilder;
use Pollora\Meta\Application\Services\MetaSchemaRepository;
use Pollora\Meta\Domain\Services\MetaValueCaster;
use Pollora\Meta\UI\Console\MetaAuditCommand;
use Pollora\Meta\UI\Console\MetaListCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Unit\Meta\Fixtures\ArrayMetaInventory;
use Tests\Unit\Meta\Fixtures\Event;
use Tests\Unit\Meta\Fixtures\MemberProfile;

/**
 * @param  array<string, mixed>  $input
 * @return array{0: int, 1: string}
 */
function runMetaCommand(Command $command, Application $app, array $input = []): array
{
    $command->setLaravel($app);
    $output = new BufferedOutput;
    $code = $command->run(new ArrayInput($input), $output);
    Container::setInstance(new Container);

    return [$code, $output->fetch()];
}

beforeEach(function (): void {
    $this->app = new Application(sys_get_temp_dir());
    $this->schemas = new MetaSchemaRepository;
    $this->schemas->add((new MetaSchemaBuilder)->build(Event::class));
    $this->schemas->add((new MetaSchemaBuilder)->build(MemberProfile::class));

    $this->app->instance(MetaSchemaRepository::class, $this->schemas);
});

it('lists the declared meta with their key, type and options', function (): void {
    [$code, $output] = runMetaCommand(new MetaListCommand, $this->app, ['--json' => true]);
    $json = json_decode($output, true);
    $capacity = array_values(array_filter($json['schemas'][0]['meta'], fn (array $meta): bool => $meta['key'] === 'capacity'))[0];

    expect($code)->toBe(0)
        ->and(array_column($json['schemas'], 'owner'))->toBe(['post "event"', 'user'])
        ->and($capacity)->toBe(['property' => 'capacity', 'key' => 'capacity', 'type' => 'int', 'default' => 0, 'flags' => ['rest']])
        ->and(array_column($json['schemas'][0]['meta'], 'type', 'key')['starts_at'])->toBe('?CarbonImmutable');
});

it('fails the list when discovery refused a declaration', function (): void {
    $this->schemas->fail('App\\Broken', 'the property needs a single type.');

    [$code, $output] = runMetaCommand(new MetaListCommand, $this->app);

    expect($code)->toBe(1)->and($output)->toContain('App\\Broken: the property needs a single type.');
});

it('audits the database, failing on unreadable values only', function (array $rows, int $expected): void {
    Functions\when('is_serialized')->justReturn(false);
    $this->app->instance(MetaAuditor::class, new MetaAuditor($this->schemas, new ArrayMetaInventory($rows), new MetaValueCaster));

    [$code, $output] = runMetaCommand(new MetaAuditCommand, $this->app, ['--json' => true]);

    expect($code)->toBe($expected)->and(json_decode($output, true))->toHaveKeys(['unreadable', 'undeclared']);
})->with([
    'clean' => [['post' => ['event' => [1 => ['capacity' => ['10']]]]], 0],
    'an undeclared key only' => [['post' => ['event' => [1 => ['old_capacity' => ['10']]]]], 0],
    'an unreadable value' => [['post' => ['event' => [1 => ['capacity' => ['ten']]]]], 1],
]);
