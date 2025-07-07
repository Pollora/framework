<?php

declare(strict_types=1);

use Pollora\Attributes\Action;
use Pollora\Attributes\PostType;
use Pollora\Attributes\Services\AttributeProcessor;
use Pollora\Attributes\Services\AutoAttributable;

// Exemple de classe qui n'implémente pas Attributable mais utilise des attributs
#[PostType('event')]
class SimpleEventClass
{
    #[Action('init')]
    public function initialize(): void
    {
        // Logique d'initialisation
    }

    public function getName(): string
    {
        return 'Event';
    }
}

// Classe qui implémente déjà Attributable (pour comparaison)
#[PostType('book')]
class BookClass implements \Pollora\Attributes\Contracts\Attributable
{
    #[Action('wp_loaded')]
    public function setup(): void
    {
        // Setup logic
    }

    public function getSupportedDomains(): array
    {
        return ['post_type', 'hook'];
    }

    public function supportsDomain(string $domain): bool
    {
        return in_array($domain, $this->getSupportedDomains());
    }
}

// Classe sans attributs
class PlainClass
{
    public function doSomething(): string
    {
        return 'something';
    }
}

it('automatically wraps classes without Attributable interface', function () {
    $autoAttributable = new AutoAttributable(new SimpleEventClass);

    expect($autoAttributable->getSupportedDomains())->toContain('post_type', 'hook');
    expect($autoAttributable->supportsDomain('post_type'))->toBeTrue();
    expect($autoAttributable->supportsDomain('hook'))->toBeTrue();
    expect($autoAttributable->supportsDomain('taxonomy'))->toBeFalse();
});

it('forwards method calls to original instance', function () {
    $autoAttributable = new AutoAttributable(new SimpleEventClass);

    expect($autoAttributable->getName())->toBe('Event');
});

it('detects domains from PostType attribute', function () {
    $autoAttributable = new AutoAttributable(new SimpleEventClass);

    expect($autoAttributable->getSupportedDomains())->toContain('post_type');
});

it('detects domains from Action attribute on methods', function () {
    $autoAttributable = new AutoAttributable(new SimpleEventClass);

    expect($autoAttributable->getSupportedDomains())->toContain('hook');
});

it('works with classes that already implement Attributable', function () {
    $bookInstance = new BookClass;

    // Si la classe implémente déjà Attributable, elle ne devrait pas être wrappée
    expect($bookInstance)->toBeInstanceOf(\Pollora\Attributes\Contracts\Attributable::class);
    expect($bookInstance->getSupportedDomains())->toContain('post_type', 'hook');
});

it('processor creates AutoAttributable wrapper automatically', function () {
    // Mock des services requis
    $registry = Mockery::mock(\Pollora\Attributes\Services\AttributeRegistry::class);
    $resolver = Mockery::mock(\Pollora\Attributes\Services\AttributeResolver::class);
    $validator = Mockery::mock(\Pollora\Attributes\Services\AttributeValidator::class);
    $orchestrator = Mockery::mock(\Pollora\Attributes\Services\AttributeOrchestrator::class);

    $processor = new AttributeProcessor($registry, $resolver, $validator, $orchestrator);

    // Mock le resolver pour retourner des attributs vides (pour simplifier le test)
    $resolver->shouldReceive('resolveAttributesByDomain')
        ->andReturn([]);

    // Mock le validator pour ne rien faire
    $validator->shouldReceive('validateDomainCompatibility')
        ->andReturn();

    // Mock l'orchestrator pour ne rien faire
    $orchestrator->shouldReceive('processByDomain')
        ->andReturn();

    // Tester que le processeur peut traiter une classe sans interface Attributable
    $context = $processor->processClass(SimpleEventClass::class);

    expect($context)->toBeInstanceOf(\Pollora\Attributes\Contracts\AttributeContextInterface::class);

    // L'instance originale doit être wrappée dans AutoAttributable
    $originalInstance = $context->getOriginalInstance();
    expect($originalInstance)->toBeInstanceOf(AutoAttributable::class);
    expect($originalInstance->getOriginalInstance())->toBeInstanceOf(SimpleEventClass::class);
});

it('can check if class has processable attributes', function () {
    $registry = Mockery::mock(\Pollora\Attributes\Services\AttributeRegistry::class);
    $resolver = Mockery::mock(\Pollora\Attributes\Services\AttributeResolver::class);
    $validator = Mockery::mock(\Pollora\Attributes\Services\AttributeValidator::class);
    $orchestrator = Mockery::mock(\Pollora\Attributes\Services\AttributeOrchestrator::class);

    $processor = new AttributeProcessor($registry, $resolver, $validator, $orchestrator);

    // Classe avec attributs
    expect($processor->hasProcessableAttributes(SimpleEventClass::class))->toBeTrue();

    // Classe sans attributs
    expect($processor->hasProcessableAttributes(PlainClass::class))->toBeFalse();
});

it('auto-attributable returns original instance', function () {
    $originalInstance = new SimpleEventClass;
    $autoAttributable = new AutoAttributable($originalInstance);

    expect($autoAttributable->getOriginalInstance())->toBe($originalInstance);
});

afterEach(function () {
    Mockery::close();
});
