# Contributing to Pollora Framework

Thank you for considering contributing to the Pollora framework! This document
outlines our guidelines and conventions.

## Getting Started

1. **Fork** the repository and clone your fork.
2. Install PHP and Composer dependencies:
   ```bash
   composer install
   ```
3. Install Node dependencies (for commit hooks and formatting):
   ```bash
   npm install
   ```
4. Create a new branch from `develop` following the Gitflow pattern, e.g.
   `feature/my-awesome-feature` or `hotfix/important-fix`.

## Coding Standards

- Follow **Laravel** coding conventions. The project is formatted using
  [Laravel Pint](https://laravel.com/docs/pint). Run `composer lint` to check
  your code style.
- The WordPress coding standard is **not** used. All PHP code should follow
  Laravel guidelines.
- The framework is written using **hexagonal architecture**. New features should
  respect this structure by isolating domain logic from infrastructure and
  framework concerns.

## Tests

- Add unit tests whenever you introduce new behaviour or fix a bug.
- The test suite uses [Pest](https://pestphp.com). Run all checks with:
  ```bash
  composer test
  ```
  This executes Rector, Pint, PHPStan and the unit tests.
- Pull requests must pass the CI pipeline. Aim for 100% coverage as enforced by
  the test configuration.

## Commit Messages

- Commits are validated by **commitlint**. Use the conventional commit format,
  for example `feat: add new module` or `fix: handle invalid input`.
- Run `npm install` once to enable the Git hooks that perform this check.

## Pull Requests

- Target the `develop` branch and describe the feature or fix clearly.
- Explain **how to test** the change. Include relevant commands or steps.
- Ensure your branch is up to date with `develop` and that the CI checks pass.

## Additional Notes
- We follow Gitflow for branch management: start feature branches from
   `develop`. Release branches are handled by the project maintainers.
- Keep the documentation up to date when you change public behaviour.

We appreciate your help in making the Pollora framework better!
