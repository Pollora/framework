---
name: "Refonte WooCommerce Hexagonale"
about: "Planifier la refonte du dossier src/Plugins/WooCommerce selon l'architecture hexagonale"
title: "Refonte WooCommerce vers architecture hexagonale"
labels: enhancement
assignees: ''
---

# Objectif

Mettre à jour le module WooCommerce pour suivre l'architecture hexagonale de Pollora, en prenant exemple sur `src/Route`.

# Structure proposée

```
src/Plugins/WooCommerce/
├── Application/
│   └── UseCases/
│       └── RegisterWooCommerceHooksUseCase.php
├── Domain/
│   ├── Contracts/
│   │   ├── WooCommerceIntegrationInterface.php
│   │   └── TemplateResolverInterface.php
│   ├── Models/
│   │   └── Template.php
│   └── Services/
│       └── WooCommerceService.php
├── Infrastructure/
│   ├── Adapters/
│   │   └── WordPressWooCommerceAdapter.php
│   ├── Providers/
│   │   └── WooCommerceServiceProvider.php
│   └── Services/
│       ├── WooCommerce.php
│       └── WooCommerceTemplateResolver.php
└── UI/
    └── Console/ (si nécessaire)
```

# Principes de refonte

1. **Isolation du domaine**
   - Définir des interfaces dans `Domain/Contracts` pour décrire les capacités attendues.
   - Implémenter la logique pure dans `Domain/Services` lorsque possible.
2. **Cas d'usage applicatifs**
   - Créer `RegisterWooCommerceHooksUseCase` pour enregistrer toutes les actions/filtres via l'adaptateur WordPress.
   - Exécuter ce use case depuis le service provider.
3. **Adaptateurs et infrastructure**
   - Regrouper les appels WordPress dans `Infrastructure/Adapters`.
   - `WooCommerceServiceProvider` lie les interfaces aux implémentations et appelle le use case au boot.
4. **Réutilisation du code existant**
   - Transformer `WooCommerce.php` et `WooCommerceTemplateResolver.php` en services implémentant les interfaces du domaine.
5. **Tests et extensibilité**
   - Tester chaque couche en isolant les dépendances.
   - Permettre l'extension via les interfaces du domaine.

# Étapes de migration

1. Créer l'arborescence ci-dessus et déplacer les fichiers existants.
2. Introduire les interfaces dans `Domain/Contracts` et adapter les classes existantes.
3. Ajouter le use case d'enregistrement des hooks et le lancer dans le provider.
4. Mettre à jour les namespaces et l'autoloading de `composer.json` si besoin.
5. Écrire des tests unitaires pour garantir le comportement actuel.

En appliquant ces étapes, le module WooCommerce sera en adéquation avec l'architecture hexagonale de Pollora.
