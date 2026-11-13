<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Pollora\Foundation\Console\Commands\MakeModelCommand;
use Pollora\Foundation\Providers\ArtisanServiceProvider;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * @param  array<string, mixed>  $input
 * @return array{status: int, output: string}
 */
function runMakeModel(Application $app, array $input): array
{
    $command = new MakeModelCommand(new Filesystem);
    $command->setLaravel($app);
    $output = new BufferedOutput;
    $status = $command->run(new ArrayInput($input), $output);

    return ['status' => $status, 'output' => $output->fetch()];
}

beforeEach(function (): void {
    $this->base = sys_get_temp_dir().'/pollora-make-model-'.uniqid();
    mkdir($this->base.'/app', 0777, true);
    file_put_contents($this->base.'/composer.json', json_encode(['autoload' => ['psr-4' => ['App\\' => 'app/']]]));
    $this->app = new Application($this->base);
    $this->app->instance('config', new Repository([]));
});

afterEach(function (): void {
    (new Filesystem)->deleteDirectory($this->base);
    Container::setInstance(null);
});

describe('pollora:make:model', function (): void {
    it('writes an Eloquent model in app/Models with its table and fillable attributes', function (): void {
        $result = runMakeModel($this->app, ['name' => 'BlogPost', '--fillable' => 'title, body']);

        $file = $this->base.'/app/Models/BlogPost.php';

        expect($result['status'])->toBe(0)
            ->and($file)->toBeFile()
            ->and(file_get_contents($file))
            ->toContain('namespace App\\Models;')
            ->toContain('class BlogPost extends Model')
            ->toContain("protected \$table = 'blog_posts';")
            ->toContain("protected \$fillable = ['title', 'body'];")
            ->and($result['output'])->toContain('Creating Model in app');
    });

    it('leaves the fillable list empty when none is given', function (): void {
        runMakeModel($this->app, ['name' => 'Tag']);

        expect(file_get_contents($this->base.'/app/Models/Tag.php'))->toContain('protected $fillable = [];');
    });

    it('writes the intermediate table stub with --pivot', function (): void {
        runMakeModel($this->app, ['name' => 'PostTag', '--pivot' => true]);

        expect(file_get_contents($this->base.'/app/Models/PostTag.php'))
            ->toContain('class PostTag extends Pivot')
            ->not->toContain('class PostTag extends Model');
    });

    it('does not overwrite an existing model', function (): void {
        runMakeModel($this->app, ['name' => 'Tag']);
        file_put_contents($this->base.'/app/Models/Tag.php', '<?php // mine');

        $result = runMakeModel($this->app, ['name' => 'Tag']);

        expect(file_get_contents($this->base.'/app/Models/Tag.php'))->toBe('<?php // mine')
            ->and($result['output'])->toContain('Model already exists');
    });

    it('prefers a stub published at the project root over the bundled one', function (): void {
        mkdir($this->base.'/stubs', 0777, true);
        file_put_contents($this->base.'/stubs/model.stub', "<?php\nnamespace {{ namespace }};\nclass {{ model }} {} // custom {{ table }}\n");

        runMakeModel($this->app, ['name' => 'Tag']);

        expect(file_get_contents($this->base.'/app/Models/Tag.php'))->toContain('// custom tags');
    });

    it('refuses a name PHP reserves', function (): void {
        $result = runMakeModel($this->app, ['name' => 'Class']);

        expect($result['status'])->toBe(0)
            ->and($result['output'])->toContain('reserved by PHP')
            ->and(is_dir($this->base.'/app/Models'))->toBeFalse();
    });
});

describe('ArtisanServiceProvider', function (): void {
    it('adds the model generator to Artisan when running in the console', function (): void {
        $app = Mockery::mock(Application::class);
        $app->shouldReceive('runningInConsole')->andReturn(true);
        ArtisanServiceProviderProbe::$captured = null;

        (new ArtisanServiceProviderProbe($app))->register();

        expect(ArtisanServiceProviderProbe::$captured)->toBe([MakeModelCommand::class]);
    });

    it('stays silent outside the console', function (): void {
        $app = Mockery::mock(Application::class);
        $app->shouldReceive('runningInConsole')->andReturn(false);
        ArtisanServiceProviderProbe::$captured = null;

        (new ArtisanServiceProviderProbe($app))->register();

        expect(ArtisanServiceProviderProbe::$captured)->toBeNull();
    });
});

class ArtisanServiceProviderProbe extends ArtisanServiceProvider
{
    /** @var list<string>|null */
    public static ?array $captured = null;

    public function commands($commands): void
    {
        self::$captured = (array) $commands;
    }
}
