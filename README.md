# msstc4symfony/slo-bridge-profiling

![Build Status](https://github.com/msstc4symfony/slo-bridge-profiling/actions/workflows/checks.yml/badge.svg?branch=main)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

Opens a msstc4symfony/profiling-bundle span around every msstc4symfony/slo-bundle operation

## Compatibility

| Bundle | PHP  | Symfony  |
|--------|------|----------|
| 1.x    | 8.2+ | 7.4, 8.x |

Symfony 8.x needs PHP 8.4; on PHP 8.2 and 8.3 Composer installs Symfony 7.4.

## Installation

The package is not on Packagist yet, so register its GitHub repository first:

```sh
composer config repositories.msstc4symfony-slo-bridge-profiling vcs https://github.com/msstc4symfony/slo-bridge-profiling
composer require msstc4symfony/slo-bridge-profiling
```

Symfony Flex registers the bundle automatically; without Flex add it to `config/bundles.php`:

```php
Msstc4Symfony\SloBridgeProfiling\SloBridgeProfilingBundle::class => ['all' => true],
```

## Usage

The bridge has no configuration. With `SloBundle` and `ProfilingBundle` registered, every
`OperationMonitorInterface::measure()` / `start()` of an operation declared in `msstc4symfony_slo` opens a
profiling span `operation <name>`; its end context carries `outcome` (`success`, `expected_failure`, `error`) and,
on failure, `exception` (the exception class). Undeclared or invalid names open no span.

## Layers

`src/` follows the canonical layout of [bundle-standard](https://github.com/msstc4symfony/bundle-standard)
(`templates/deptrac.yaml`). The list is closed — `verify-standard` rejects any other directory:

- `Domain/` — data in `Entity/`, `Document/`, `ValueObject/`, `Enum/`, `Event/`, `Repository/`, `DocumentRepository/`;
  operations in `Service/`, `Helper/`, `Specification/`;
- `Application/`, `Infrastructure/`, `Presentation/`, `Framework/`;
- `DependencyInjection/` (compiler passes in `CompilerPass/`);
- `Resources/` (configuration only, no classes) and the bundle class `<Name>Bundle.php`.

Empty layers need no directory. `deptrac.yaml` starts with the full canonical ruleset; narrow it as the bundle's
layers settle, never widen it.

## Local development

```sh
make install-ci                        # composer-ci.json profile with every CI tool
COMPOSER=composer-ci.json make check   # lint, PHPStan, CS, composer validate/audit, Rector, deptrac
make test                              # unit + integration
```

## License

MIT — see [LICENSE](LICENSE).
