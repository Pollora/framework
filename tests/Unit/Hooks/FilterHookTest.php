<?php

namespace Tests\Unit\Hooks;

use Pollora\Hook\Filter;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use WP;

// Intégration de Mockery avec PHPUnit
uses(MockeryPHPUnitIntegration::class);

beforeEach(function () {
    // Réinitialisation des mocks WordPress avant chaque test
    setupWordPressMocks();

    // Instance fraîche de Filter pour chaque test
    $this->filter = new Filter();
});

// Groupe de tests pour la méthode add
describe('Filter::add', function () {
    it('ajoute un hook avec les valeurs par défaut', function () {
        $callback = function ($content) {
            return $content . ' filtered';
        };

        // Configuration de l'expectation pour add_filter
        WP::$wpFunctions->shouldReceive('add_filter')
            ->with('the_content', \Mockery::type('callable'), 10, 1)
            ->once()
            ->andReturn(true);

        $this->filter->add('the_content', $callback);

        // Vérifie que le hook a été enregistré dans l'index
        expect($this->filter->exists('the_content'))->toBeTrue();
    });

    it('ajoute un hook avec une priorité personnalisée', function () {
        $callback = function ($content) {
            return $content . ' filtered';
        };

        // Configuration de l'expectation pour add_filter
        WP::$wpFunctions->shouldReceive('add_filter')
            ->with('the_content', \Mockery::type('callable'), 5, 1)
            ->once()
            ->andReturn(true);

        $this->filter->add('the_content', $callback, 5);

        // Vérifie que le hook a été enregistré correctement
        expect($this->filter->exists('the_content', $callback, 5))->toBeTrue();
        expect($this->filter->exists('the_content', $callback, 10))->toBeFalse();
    });

    it('détecte automatiquement le nombre d\'arguments', function () {
        $callback = function ($content, $id, $context) {
            return $content . $id . $context;
        };

        // Configuration de l'expectation pour add_filter
        // La méthode devrait détecter 3 arguments
        WP::$wpFunctions->shouldReceive('add_filter')
            ->with('the_content', \Mockery::type('callable'), 10, 3)
            ->once()
            ->andReturn(true);

        $this->filter->add('the_content', $callback);

        // Vérification que le filtre a été ajouté
        expect($this->filter->exists('the_content'))->toBeTrue();
    });
});

// Groupe de tests pour la méthode remove
describe('Filter::remove', function () {
    it('supprime un hook existant', function () {
        $callback = function ($content) {
            return $content . ' filtered';
        };

        // Ajoute d'abord le hook
        WP::$wpFunctions->shouldReceive('add_filter')
            ->with('the_content', \Mockery::type('callable'), 10, 1)
            ->once()
            ->andReturn(true);

        $this->filter->add('the_content', $callback);

        // Configure l'expectation pour remove_filter
        WP::$wpFunctions->shouldReceive('remove_filter')
            ->with('the_content', \Mockery::type('callable'), 10)
            ->once()
            ->andReturn(true);

        $this->filter->remove('the_content', $callback);

        // Vérifie que le hook a été supprimé de l'index
        expect($this->filter->exists('the_content', $callback))->toBeFalse();
    });
});

// Groupe de tests pour la méthode exists
describe('Filter::exists', function () {
    it('détecte correctement les hooks existants', function () {
        $callback = function ($content) {
            return $content . ' filtered';
        };

        // Ajoute le hook
        WP::$wpFunctions->shouldReceive('add_filter')
            ->andReturn(true);
        $this->filter->add('the_content', $callback);

        // Vérifications
        expect($this->filter->exists('the_content'))->toBeTrue();
        expect($this->filter->exists('the_content', $callback))->toBeTrue();
        expect($this->filter->exists('the_content', $callback, 10))->toBeTrue();
    });

    it('détecte correctement les hooks inexistants', function () {
        $callback = function ($content) {
            return $content . ' filtered';
        };

        // Tests basiques
        expect($this->filter->exists('nonexistent_hook'))->toBeFalse();

        // Ajoute un hook et teste des variations
        WP::$wpFunctions->shouldReceive('add_filter')
            ->andReturn(true);
        $this->filter->add('the_content', $callback);

        // Différentes combinaisons qui devraient être fausses
        $differentCallback = function ($content) { return $content; };
        expect($this->filter->exists('the_content', $differentCallback))->toBeFalse();
        expect($this->filter->exists('the_content', $callback, 20))->toBeFalse();
    });
});

// Groupe de tests pour la méthode apply
describe('Filter::apply', function () {
    it('applique correctement le filtre', function () {
        // Données de test
        $hook = 'the_content';
        $value = '<p>Original content</p>';
        $arg1 = 123;

        // Configure l'expectation
        WP::$wpFunctions->shouldReceive('apply_filters')
            ->once()
            ->with($hook, $value, $arg1)
            ->andReturn('<p>Original content</p><div>Modified!</div>');

        // Exécute et vérifie
        $result = $this->filter->apply($hook, $value, $arg1);
        expect($result)->toBe('<p>Original content</p><div>Modified!</div>');
    });

    it('maintient la valeur originale si aucun filtre n\'est appliqué', function () {
        // Données de test
        $hook = 'the_content';
        $value = '<p>Original content</p>';

        // Configure l'expectation pour simuler aucune modification
        WP::$wpFunctions->shouldReceive('apply_filters')
            ->once()
            ->with($hook, $value)
            ->andReturn($value);

        // Exécute et vérifie
        $result = $this->filter->apply($hook, $value);
        expect($result)->toBe($value);
    });
});

// Groupe de tests pour la méthode applyArray
describe('Filter::applyArray', function () {
    it('applique correctement le filtre avec un tableau d\'arguments', function () {
        // Données de test
        $hook = 'the_content';
        $value = '<p>Original content</p>';
        $args = [123, 'context'];

        // Configure l'expectation
        WP::$wpFunctions->shouldReceive('apply_filters_array')
            ->once()
            ->with($hook, array_merge([$value], $args))
            ->andReturn('<p>Modified with args</p>');

        // Exécute et vérifie
        $result = $this->filter->applyArray($hook, $value, $args);
        expect($result)->toBe('<p>Modified with args</p>');
    });

    it('gère correctement un tableau d\'arguments vide', function () {
        // Données de test
        $hook = 'the_content';
        $value = '<p>Original content</p>';
        $emptyArgs = [];

        // Configure l'expectation
        WP::$wpFunctions->shouldReceive('apply_filters_array')
            ->once()
            ->with($hook, [$value])
            ->andReturn($value);

        // Exécute et vérifie
        $result = $this->filter->applyArray($hook, $value, $emptyArgs);
        expect($result)->toBe($value);
    });
});

// Groupe de tests pour la méthode getCallbacks
describe('Filter::getCallbacks', function () {
    it('récupère les callbacks associés à un filtre', function () {
        // Prépare les callbacks attendus
        $mockCallbacks = [
            'callback1' => ['function' => 'someFunction'],
            'callback2' => ['function' => 'anotherFunction']
        ];

        // Mock global $wp_filter
        global $wp_filter;
        $wp_filter['the_content'] = (object) [
            'callbacks' => [
                10 => $mockCallbacks
            ]
        ];

        // Récupère les callbacks
        $callbacks = $this->filter->getCallbacks('the_content');

        // Vérifie les résultats
        expect($callbacks)->toBe($wp_filter['the_content']->callbacks);
    });

    it('récupère les callbacks pour une priorité spécifique', function () {
        // Prépare les callbacks attendus
        $mockCallbacks10 = [
            'callback1' => ['function' => 'someFunction']
        ];

        $mockCallbacks20 = [
            'callback2' => ['function' => 'anotherFunction']
        ];

        // Mock global $wp_filter
        global $wp_filter;
        $wp_filter['the_content'] = (object) [
            'callbacks' => [
                10 => $mockCallbacks10,
                20 => $mockCallbacks20
            ]
        ];

        // Récupère les callbacks pour la priorité 20
        $callbacks = $this->filter->getCallbacks('the_content', 20);

        // Vérifie les résultats
        expect($callbacks)->toBe($mockCallbacks20);
    });

    it('renvoie un tableau vide pour un hook inexistant', function () {
        $callbacks = $this->filter->getCallbacks('nonexistent_hook');
        expect($callbacks)->toBeArray()->toBeEmpty();
    });
});
