# CLAUDE.md

Guidance for Claude Code in this repository. Deep references live under `.claude/docs/`.

## What this is

Symfony bundle (`msstc4symfony/slo-bridge-profiling`, namespace `Msstc4Symfony\SloBridgeProfiling`, config alias
`msstc4symfony_slo_bridge_profiling`): Opens a msstc4symfony/profiling-bundle span around every
msstc4symfony/slo-bundle operation. PHP >= 8.2, Symfony 7.4 / 8.x.
Library code only.

## Common commands

slo-bundle 1.0 is released: `COMPOSER=composer-ci.json composer install` resolves it from the vcs repo. For
unreleased slo-bundle work use the git-ignored local profile `COMPOSER=composer-local.json composer install`
(path repo `../monitor-bundle`).

- `make check` — `php -l`, PHPStan level 10, PHP-CS-Fixer, `composer validate --strict`,
  `composer audit`, Rector dry-run, deptrac. Run as `COMPOSER=composer-ci.json make check`.
- `make test` — unit + integration suites (`tests/Integration/KernelBridgeTest` boots a real kernel with every
  bundle, logic-bundle only when installed); `make infection`, `make fix`,
  `make regenerate-baseline`.
- `php ../bundle-standard/bin/verify-standard.php .` — standard compliance (the same check CI runs).

## Architecture in 60 seconds

- `SloBridgeProfilingBundle` (`AbstractBundle`, no config tree) adds `RequireBundlesPass` and imports
  `src/Resources/config/services.php`.
- `Infrastructure/Profiling/ProfiledOperationMonitor` decorates slo-bundle's `OperationMonitorInterface` and opens a
  span per declared operation.
- Layers and allowed dependencies are enforced by `deptrac.yaml`.

Details: `.claude/docs/architecture.md`. **Read `.claude/docs/known-issues.md` before chasing a
"weird" failure.**

## Pointers

- `.claude/docs/architecture.md` — layers, wiring, service ids.
- `.claude/docs/conventions.md` — layout, tests, commits.
- `.claude/docs/integrations.md` — slo-bundle, profiling-bundle, Monolog, metrics- and tracing-bridge-profiling.
- `.claude/docs/known-issues.md` — Symfony/CI traps, kernel-test gotchas.
