---
trigger: always_on
description: 
globs: 
---

# Pollora & PHP Coding Standards for AI Code Assistants

This document outlines the Pollora and PHP development standards, structured for optimal compatibility with AI tools such as GitHub Copilot, Cursor, and Claude Code. These conventions are adapted from Spatie’s Laravel & PHP guidelines.

## Laravel Foundation

**Always follow official Laravel conventions first.** Deviation is acceptable only when justified by a valid technical reason.

## PHP Standards

* Adhere to PSR-1, PSR-2, and PSR-12
* Use `camelCase` for all internal string keys and parameters
* Prefer `?Type` over `Type|null`
* Explicitly return `void` when nothing is returned

## Class Design

* Use typed properties, not docblocks
* Use constructor property promotion when applicable
* Declare one trait per line

## Typing & Docblocks

* Favor typed properties over annotations
* Define return types, including `void`
* Use generics for iterable types:

  ```php
  /** @return Collection<int, User> */
  public function users(): Collection
  ```

### Docblock Rules

* Avoid docblocks for fully typed methods (unless needed for clarity)
* Always import class names in docblocks:

  ```php
  use Pollora\Url\Url;
  /** @return Url */
  ```
* Use one-line annotations where applicable: `/** @var string */`
* For multi-type hints, list the most common type first:

  ```php
  /** @var Collection|Custom\Collection */
  ```
* If one parameter has a docblock, all should
* Always define key/value for iterables:

  ```php
  /**
   * @param array<int, CustomObject> $items
   * @param int $id
   */
  function process(array $items, int $id): void {}
  ```
* Use array shape syntax for structured arrays:

  ```php
  /** @return array{
       first: SomeClass,
       second: SomeClass
  } */
  ```

## Control Flow

* Handle edge cases first (happy path last)
* Prefer early return over `else`
* Avoid compound conditions; separate `if` statements improve clarity
* Always use curly braces `{}` even for single-line blocks
* Format ternary operators for readability

```php
if (! $user) {
    return null;
}

if (! $user->isActive()) {
    return null;
}

// Handle active user
```

```php
$name = $isFoo ? 'foo' : 'bar';

$result = $item instanceof Model
    ? $item->name
    : 'default';
```

## Pollora-Specific Conventions

### Routes

* URL paths: kebab-case (`/open-source`)
* Route names: camelCase (`->name('openSource')`)
* Parameters: camelCase (`{userId}`)
* Controller references: tuple syntax `[Controller::class, 'method']`

### Controllers

* Use plural resource names (`PostsController`)
* Stick to standard CRUD methods
* Isolate non-CRUD logic into dedicated controllers

### Configuration

* File names: kebab-case (`pdf-generator.php`)
* Keys: snake\_case (`chrome_path`)
* Extend `config/services.php` instead of creating new config files
* Use `config()` for access, not `env()` (outside config files)

### Artisan Commands

* Use kebab-case (`delete-old-records`)
* Always give user feedback (`$this->comment('Done')`)
* Output before processing for easier debugging
* Show progress and final summary

```php
$items->each(function (Item $item) {
    $this->info("Processing ID {$item->id}...");
    $this->processItem($item);
});

$this->comment("Total: {$items->count()} items processed.");
```

## String Handling

* Prefer interpolation over concatenation

## Enums

* Enum values use PascalCase

## Comments

* Write expressive code to reduce comment need
* When needed, format clearly:

```php
// Brief description

/*
 * Multiline block
 */
```

* Replace comments with descriptive method names when possible

## Whitespace

* Separate logical blocks with blank lines
* No extra spacing between brackets
* Group related single-line operations without separation

## Validation

* Use array syntax for rules (makes custom rules easier to inject):

  ```php
  return [
      'email' => ['required', 'email'],
  ];
  ```
* Custom rules use snake\_case:

  ```php
  Validator::extend('organisation_type', fn($attribute, $value) => OrganisationType::isValid($value));
  ```

## Blade Templates

* Use 4-space indentation
* No space after control structures:

  ```blade
  @if($active)
      ...
  @endif
  ```

## Authorization

* Use camelCase in policies: `Gate::define('editPost', ...)`
* Favor CRUD naming; prefer `view` over `show`

## Translations

* Use `__()` instead of `@lang`

## API Routing

* Use plural resources: `/errors`
* Use kebab-case: `/error-occurrences`
* Avoid deep nesting:

  ```
  /error-occurrences/1
  /errors/1/occurrences
  ```

## Testing

* Where practical, co-locate related test classes
* Use descriptive method names
* Follow arrange–act–assert pattern

---

## Quick Reference

### Naming

| Element             | Convention  | Example                    |
| ------------------- | ----------- | -------------------------- |
| Classes             | PascalCase  | `UserController`           |
| Methods / Variables | camelCase   | `getUserName`, `$userData` |
| Routes              | kebab-case  | `/open-source`             |
| Config Files        | kebab-case  | `pdf-generator.php`        |
| Config Keys         | snake\_case | `chrome_path`              |
| Artisan Commands    | kebab-case  | `delete-old-records`       |

### Structure

* Controllers: `PostsController`
* Views: `openSource.blade.php`
* Jobs: `SendEmailNotification`
* Events: `UserRegistered`
* Listeners: `SendWelcomeMailListener`
* Commands: `PublishScheduledPostsCommand`
* Mailables: `AccountActivatedMail`
* Resources: `UsersResource`
* Enums: `BookingStatus`

### Migrations

* Only use `up()` methods, no need for `down()` unless rollback is required

---

## Code Quality Checklist

* Typed properties > docblocks
* Early return > nested logic
* Use constructor property promotion
* Avoid `else` where possible
* Use string interpolation
* Always use braces for control structures