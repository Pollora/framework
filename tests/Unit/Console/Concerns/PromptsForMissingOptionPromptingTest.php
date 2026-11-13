<?php

declare(strict_types=1);

use Laravel\Prompts\Key;
use Laravel\Prompts\Prompt;
use Pollora\Console\Concerns\PromptsForMissingOption;
use Pollora\Console\Contracts\PromptsForMissingOption as PromptsForMissingOptionContract;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Run a command whose missing options are prompted, with the keys a user would press.
 *
 * @param  array<string, mixed>  $parameters
 * @param  list<string>  $keys
 * @return array{options: array<string, mixed>, after: int}
 */
function runPromptingCommand(array $parameters, array $keys, ?Closure $closureConfig = null): array
{
    Prompt::fake($keys);

    $command = new class($closureConfig) extends Command implements PromptsForMissingOptionContract
    {
        use PromptsForMissingOption;

        /** @var array<string, mixed> */
        public array $received = [];

        public int $after = 0;

        public function __construct(private readonly ?Closure $closureConfig)
        {
            parent::__construct('demo');
            $this->addOption('name', null, InputOption::VALUE_REQUIRED);
            $this->addOption('site', null, InputOption::VALUE_REQUIRED);
            $this->addOption('publish', null, InputOption::VALUE_NEGATABLE);
            $this->addOption('kind', null, InputOption::VALUE_REQUIRED);
            $this->addOption('tags', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED);
            $this->addOption('computed', null, InputOption::VALUE_REQUIRED);
            $this->addOption('untouched', null, InputOption::VALUE_REQUIRED);
        }

        protected function execute(InputInterface $input, OutputInterface $output): int
        {
            $this->received = $input->getOptions();

            return self::SUCCESS;
        }

        protected function promptForMissingOptionsUsing(): array
        {
            return [
                'name' => ['label' => 'Name?', 'default' => '', 'validation' => 'required'],
                'site' => ['label' => 'Site?', 'default' => '', 'validation' => 'required|url'],
                'publish' => ['label' => 'Publish?', 'type' => 'confirm', 'default' => true],
                'kind' => ['label' => 'Kind?', 'type' => 'select', 'options' => ['a' => 'Alpha', 'b' => 'Beta'], 'default' => 'b'],
                'tags' => ['label' => 'Tag?', 'default' => 'x'],
                'computed' => $this->closureConfig ?? fn (): string => 'from closure',
            ];
        }

        protected function afterPromptingForMissingOptions(InputInterface $input, OutputInterface $output): void
        {
            $this->after++;
        }
    };

    $command->run(new ArrayInput($parameters), new BufferedOutput);

    return ['options' => $command->received, 'after' => $command->after];
}

describe('PromptsForMissingOption prompting', function (): void {
    it('prompts for every missing option by its type and stores the answers', function (): void {
        $result = runPromptingCommand([], [
            ...str_split('Ada'), Key::ENTER,                              // name
            ...str_split('https://ada.test'), Key::ENTER,                  // site
            Key::ENTER,                                                    // publish: default yes
            Key::ENTER,                                                    // kind: default b
            Key::BACKSPACE, ...str_split('php'), Key::ENTER,               // tags: replaces the default x
        ]);

        expect($result['options'])->toMatchArray([
            'name' => 'Ada',
            'site' => 'https://ada.test',
            'publish' => true,
            'kind' => 'b',
            'tags' => ['php'],
            'computed' => 'from closure',
            'untouched' => null,
        ])->and($result['after'])->toBe(1);
    });

    it('lets the user pick another select entry and decline a confirmation', function (): void {
        $result = runPromptingCommand([], [
            ...str_split('Ada'), Key::ENTER,
            ...str_split('https://ada.test'), Key::ENTER,
            Key::LEFT, Key::ENTER,                                         // publish: no
            Key::UP, Key::ENTER,                                           // kind: a
            Key::ENTER,                                                    // tags: keeps the default
        ]);

        expect($result['options']['publish'])->toBeFalse()
            ->and($result['options']['tags'])->toBe(['x'])
            ->and($result['options']['kind'])->toBe('a');
    });

    it('asks again while the answer fails the required and url rules', function (): void {
        $result = runPromptingCommand([], [
            Key::ENTER,                                                    // name empty: refused
            ...str_split('Ada'), Key::ENTER,
            ...str_split('bad'), Key::ENTER,                               // refused: not a URL
            Key::BACKSPACE, Key::BACKSPACE, Key::BACKSPACE,
            ...str_split('https://ok.test'), Key::ENTER,
            Key::ENTER, Key::ENTER, Key::ENTER,
        ]);

        expect($result['options']['name'])->toBe('Ada')
            ->and($result['options']['site'])->toBe('https://ok.test');
    });

    it('does not prompt for options that were given', function (): void {
        $result = runPromptingCommand(
            ['--name' => 'Given', '--site' => 'https://given.test', '--publish' => true, '--kind' => 'a', '--tags' => ['one'], '--computed' => 'mine'],
            [],
        );

        expect($result['options'])->toMatchArray([
            'name' => 'Given',
            'site' => 'https://given.test',
            'publish' => true,
            'kind' => 'a',
            'tags' => ['one'],
            'computed' => 'mine',
        ])->and($result['after'])->toBe(0);
    });

    it('asks only for what is missing', function (): void {
        $result = runPromptingCommand(
            ['--name' => 'Given', '--site' => 'https://given.test', '--publish' => true, '--kind' => 'a', '--computed' => 'mine'],
            [Key::BACKSPACE, ...str_split('only'), Key::ENTER],
        );

        expect($result['options']['tags'])->toBe(['only'])
            ->and($result['options']['publish'])->toBeTrue()
            ->and($result['after'])->toBe(1);
    });
});

/**
 * @param  array<string, string>  $config  Option => label (the bare string form)
 * @param  list<string>  $keys
 * @return array<string, mixed>
 */
function runBareLabelCommand(array $config, array $keys): array
{
    Prompt::fake($keys);

    $command = new class($config) extends Command implements PromptsForMissingOptionContract
    {
        use PromptsForMissingOption;

        /** @var array<string, mixed> */
        public array $received = [];

        /** @param array<string, string> $config */
        public function __construct(private readonly array $config)
        {
            parent::__construct('bare');
            $this->addOption('title', null, InputOption::VALUE_REQUIRED);
        }

        protected function execute(InputInterface $input, OutputInterface $output): int
        {
            $this->received = $input->getOptions();

            return self::SUCCESS;
        }

        protected function promptForMissingOptionsUsing(): array
        {
            return $this->config;
        }
    };

    $command->run(new ArrayInput([]), new BufferedOutput);

    return $command->received;
}

describe('PromptsForMissingOption bare configuration', function (): void {
    it('asks a text question from a bare label, with no default', function (): void {
        $received = runBareLabelCommand(['title' => 'Title?'], [...str_split('Hello'), Key::ENTER]);

        expect($received['title'])->toBe('Hello');
    });

    it('asks nothing when the command declares no question', function (): void {
        $received = runBareLabelCommand([], []);

        expect($received['title'])->toBeNull();
    });
});
