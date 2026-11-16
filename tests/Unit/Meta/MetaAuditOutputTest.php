<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Pollora\Meta\Application\Services\MetaAuditor;
use Pollora\Meta\Application\Services\MetaSchemaBuilder;
use Pollora\Meta\Application\Services\MetaSchemaRepository;
use Pollora\Meta\Domain\Services\MetaValueCaster;
use Pollora\Meta\Infrastructure\Laravel\Console\MetaAuditCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Unit\Meta\Fixtures\ArrayMetaInventory;
use Tests\Unit\Meta\Fixtures\Event;

/**
 * @param  array<string, array<string, array<int, array<string, list<string>>>>>  $rows
 * @return array{0: int, 1: string}
 */
function runMetaAuditText(array $rows): array
{
    $app = new Application(sys_get_temp_dir());
    $schemas = new MetaSchemaRepository;
    $schemas->add((new MetaSchemaBuilder)->build(Event::class));

    Functions\when('is_serialized')->justReturn(false);
    $app->instance(MetaAuditor::class, new MetaAuditor($schemas, new ArrayMetaInventory($rows), new MetaValueCaster));

    $command = new MetaAuditCommand;
    $command->setLaravel($app);

    $output = new BufferedOutput;
    $code = $command->run(new ArrayInput([]), $output);
    Container::setInstance(new Container);

    return [$code, $output->fetch()];
}

describe('pollora:meta:audit, human output', function (): void {
    it('is happy when every stored value reads as its declared type', function (): void {
        [$code, $output] = runMetaAuditText(['post' => ['event' => [1 => ['capacity' => ['10']]]]]);

        expect($code)->toBe(0)
            ->and($output)->toContain('Every stored value reads as its declared type.')
            ->and($output)->not->toContain('declared by no #[Meta]');
    });

    it('names the property, the objects and the share of values that read as the default', function (): void {
        [$code, $output] = runMetaAuditText(['post' => ['event' => [
            1 => ['capacity' => ['ten']],
            2 => ['capacity' => ['12']],
        ]]]);

        expect($code)->toBe(1)
            ->and($output)->toContain('1 meta have stored values that read as the default.')
            ->and($output)->toContain('Event::$capacity')
            ->and($output)->toContain('1 / 2 object(s)')
            ->and($output)->toContain('objects 1');
    });

    it('lists the keys no #[Meta] declares without failing for them', function (): void {
        [$code, $output] = runMetaAuditText(['post' => ['event' => [1 => ['old_capacity' => ['10'], 'legacy' => ['x']]]]]);

        expect($code)->toBe(0)
            ->and($output)->toContain('2 key(s) stored on your post types and taxonomies are declared by no #[Meta]')
            ->and($output)->toContain('old_capacity')
            ->and($output)->toContain('1 object(s)');
    });
});
