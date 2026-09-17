<?php

declare(strict_types=1);

use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Container\Container;
use Pollora\Console\Concerns\PromptsForMissingOption;
use Pollora\Console\Contracts\PromptsForMissingOption as PromptsForMissingOptionContract;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Run a command declaring prompt defaults and return the options it ended up with.
 *
 * @param  array<string, mixed>  $parameters
 * @return array<string, mixed>
 */
function runCommandWithPromptDefaults(array $parameters, bool $interactive): array
{
    $command = new #[Signature('make:thing {--author=} {--uri=} {--label=} {--tags=*}')] class extends Command implements PromptsForMissingOptionContract
    {
        use PromptsForMissingOption;

        /** @var array<string, mixed> */
        public array $received = [];

        public function handle(): int
        {
            $this->received = $this->options();

            return self::SUCCESS;
        }

        protected function promptForMissingOptionsUsing(): array
        {
            return [
                'author' => ['label' => 'Author?', 'default' => 'Pollora'],
                'uri' => ['URI?', 'https://pollora.dev'],
                'label' => 'Label?',
                'tags' => ['label' => 'Tags?', 'default' => 'starter'],
            ];
        }

        // Stub interact() so the interactive run does not prompt
        protected function interact($input, $output): void {}
    };

    $container = new class extends Container
    {
        public function runningUnitTests(): bool
        {
            return false;
        }
    };
    $command->setLaravel($container);

    $input = new ArrayInput($parameters);
    $input->setInteractive($interactive);

    $command->run($input, new BufferedOutput);

    return $command->received;
}

describe('PromptsForMissingOption', function (): void {
    beforeEach(function (): void {
        $this->trait = new class
        {
            use PromptsForMissingOption {
                buildValidationClosure as public;
                promptForMissingOptionsUsing as public;
            }

            // Stub parent::interact
            public function interact($input, $output): void {}
        };
    });

    describe('buildValidationClosure', function (): void {
        it('returns null when validation is null', function (): void {
            expect($this->trait->buildValidationClosure(null, 'name'))->toBeNull();
        });

        it('returns the closure as-is when given a Closure', function (): void {
            $closure = fn ($value): ?string => null;

            $result = $this->trait->buildValidationClosure($closure, 'name');

            expect($result)->toBe($closure);
        });

        it('builds required validation from string', function (): void {
            $closure = $this->trait->buildValidationClosure('required', 'name');

            expect($closure)->toBeInstanceOf(Closure::class);
            expect($closure(''))->toBe('The name is required.');
            expect($closure('valid'))->toBeNull();
        });

        it('builds url validation from string', function (): void {
            $closure = $this->trait->buildValidationClosure('url', 'website');

            expect($closure('not-a-url'))->toBe('The website must be a valid URL.');
            expect($closure('https://example.com'))->toBeNull();
            expect($closure(''))->toBeNull(); // empty is OK for url-only
        });

        it('builds combined required|url validation', function (): void {
            $closure = $this->trait->buildValidationClosure('required|url', 'endpoint');

            expect($closure(''))->toBe('The endpoint is required.');
            expect($closure('not-url'))->toBe('The endpoint must be a valid URL.');
            expect($closure('https://api.example.com'))->toBeNull();
        });

        it('returns null for non-string non-closure validation', function (): void {
            expect($this->trait->buildValidationClosure(42, 'field'))->toBeNull();
            expect($this->trait->buildValidationClosure([], 'field'))->toBeNull();
        });
    });

    describe('promptForMissingOptionsUsing', function (): void {
        it('returns empty array by default', function (): void {
            expect($this->trait->promptForMissingOptionsUsing())->toBe([]);
        });
    });

    describe('defaults without interaction', function (): void {
        it('fills missing options with their prompt defaults', function (): void {
            $options = runCommandWithPromptDefaults([], interactive: false);

            expect($options['author'])->toBe('Pollora')
                ->and($options['uri'])->toBe('https://pollora.dev')
                ->and($options['tags'])->toBe(['starter']);
        });

        it('keeps options given on the command line', function (): void {
            $options = runCommandWithPromptDefaults(['--author' => 'Acme'], interactive: false);

            expect($options['author'])->toBe('Acme');
        });

        it('leaves options without a default unset', function (): void {
            expect(runCommandWithPromptDefaults([], interactive: false)['label'])->toBeNull();
        });

        it('does not apply defaults when the command can prompt', function (): void {
            expect(runCommandWithPromptDefaults([], interactive: true)['author'])->toBeNull();
        });
    });
});
