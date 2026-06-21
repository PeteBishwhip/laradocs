# Contributing

Contributions are welcome and will be fully credited.

## Pull requests

- **Tests are required.** This package maintains 100% test coverage; add Pest
  tests for any new behaviour.
- **Static analysis must pass** at PHPStan's maximum level.
- **Code style** is enforced with Laravel Pint.

Run the full quality suite before opening a PR:

```bash
composer test            # pint --test + phpstan + pest
composer test:coverage   # enforces 100% coverage
```

## Setup

```bash
git clone https://github.com/petebishwhip/laradocs
cd laradocs
composer install
composer serve   # preview the bundled docs site
```

## Versioning

This project follows [Semantic Versioning](https://semver.org). Breaking changes
land in major releases only. Supported Laravel versions: 11, 12 and 13. Supported
PHP versions: 8.3, 8.4 and 8.5.
