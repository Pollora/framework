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
- **Title the pull request as a conventional commit.** CI validates the title,
  not just your commits, and a title like `Fix the thing` fails the
  **Validate PR title** check without saying much about why — hence this list.
  The format is `type: summary`, with an optional scope:

  ```
  fix: keep the theme root out of the public head
  feat(routing): resolve the template hierarchy before the fallback
  docs: document the pull request title convention
  ```

  Allowed types: `build`, `chore`, `ci`, `docs`, `feat`, `fix`, `hotfix`,
  `perf`, `refactor`, `release`, `revert`, `style`, `test`. This is the same
  vocabulary commitlint enforces on your commits, plus `release`, which is
  reserved for maintainers.
- Explain **how to test** the change. Include relevant commands or steps.
- Ensure your branch is up to date with `develop` and that the CI checks pass.
- The **Validate Changelog** job only runs on pull requests into `main`, which
  are release pull requests cut by maintainers. Contributing to `develop` never
  trips it.

## Changelog

All notable changes are documented in the [CHANGELOG](CHANGELOG.md). When tagging a new version, update the changelog to move items from `[Unreleased]` to the new version section with the release date.

## Additional Notes
- We follow Gitflow for branch management: start feature branches from
   `develop`. Release branches are handled by the project maintainers.
- Keep the documentation up to date when you change public behaviour.

We appreciate your help in making the Pollora framework better!
