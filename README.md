# msstc4symfony/slo-bridge-profiling

![Build Status](https://github.com/msstc4symfony/slo-bridge-profiling/actions/workflows/checks.yml/badge.svg?branch=main)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

Opens a [profiling-bundle](https://github.com/msstc4symfony/profiling-bundle) span around every operation that
[slo-bundle](https://github.com/msstc4symfony/slo-bundle) measures, so a declared SLO operation shows up in the span
tree of the request, command or message that ran it, ended with the same outcome the SLO counted.

## Compatibility

| Bundle      | PHP  | Symfony  | slo-bundle   | profiling-bundle | metrics-bundle |
|-------------|------|----------|--------------|------------------|----------------|
| 1.0.0-rc.1  | 8.2+ | 7.4, 8.x | ^1.0.0-rc.2  | ^2.3.1           | ^2.3           |

Symfony 8.x needs PHP 8.4; on PHP 8.2 and 8.3 Composer installs Symfony 7.4. logic-bundle is optional: with it,
exceptions implementing its `ExpectedFailureInterface` (or classified by its `ActionOutcome`) are expected failures.

## Installation

The packages are not on Packagist yet, so register their GitHub repositories first (metrics-bundle needs
sql-labeller):

```sh
composer config repositories.msstc4symfony-slo vcs https://github.com/msstc4symfony/slo-bundle
composer config repositories.msstc4symfony-metrics vcs https://github.com/msstc4symfony/metrics-bundle
composer config repositories.msstc4symfony-sql-labeller vcs https://github.com/msstc4symfony/sql-labeller
composer config repositories.msstc4symfony-profiling vcs https://github.com/msstc4symfony/profiling-bundle
composer config repositories.msstc4symfony-slo-bridge-profiling vcs https://github.com/msstc4symfony/slo-bridge-profiling
composer require msstc4symfony/slo-bridge-profiling:^1.0.0-rc.1 msstc4symfony/slo-bundle:^1.0.0-rc.2
```

Naming slo-bundle explicitly lets Composer accept its release candidate under `minimum-stability: stable` (only
root requirements may lower stability); alternatively set `minimum-stability: RC` with `prefer-stable: true`.

Register the bridge in `config/bundles.php` alongside the bundles it connects (order does not matter):

```php
Msstc4Symfony\MetricsBundle\MetricsBundle::class => ['all' => true],
Msstc4Symfony\SloBundle\SloBundle::class => ['all' => true],
Msstc4Symfony\ProfilingBundle\ProfilingBundle::class => ['all' => true],
Msstc4Symfony\SloBridgeProfiling\SloBridgeProfilingBundle::class => ['all' => true],
```

metrics-bundle is a direct requirement: slo-bundle writes its SLO events through it, so MetricsBundle must be
registered next to SloBundle. Nothing to configure. Without SloBundle or ProfilingBundle the container fails to compile with a message naming
the missing bundle.

## The span

The bridge decorates slo-bundle's `OperationMonitorInterface`. For every `measure()` and `start()` of an operation
declared under `msstc4symfony_slo.operations`:

- Message: `operation <name>`, e.g. `operation payment.validation`. An operation run inside another one gets a
  child span of the outer operation's span (or of the request/command/message span around it).
- End context: `outcome` — `success`, `expected_failure` or `error`, classified exactly like the SLO event — plus
  `exception` (its `get_debug_type()`, so `DomainException@anonymous` for an anonymous class) when the operation
  failed with an exception.
- `measure()` ends the span when the closure returns or throws. A handle from `start()` ends its span in `end()`,
  after the SLO event is recorded; a handle that is never ended leaves its span to profiling-bundle's implicit end
  (when the parent span ends or the profiler is reset); slo-bundle records no event for it either.
- Handles of nested operations should end innermost first. An outer handle ended before an inner one ends the inner
  span implicitly (end context `profiling_implicit_end: true`, no `outcome`); the inner handle's later `end()` still
  records its SLO event with the right outcome, only the span has lost it.
- The span follows the outcome the bridge sees, not whether slo-bundle recorded an event: if slo-bundle falls back
  to a no-op handle for a declared name (for example because its clock fails), the span is still ended with an
  outcome while no SLO event is written; slo-bundle logs that fallback.

Only declared names get a span. An undeclared, empty or invalid name runs the operation without a span (slo-bundle
logs a warning and records nothing): span messages must stay bounded by the configuration, because
[metrics-bridge-profiling](https://github.com/msstc4symfony/metrics-bridge-profiling) turns every distinct message
into a time series of `symfony_profiling_span_duration_histogram_seconds{message}`.

Select operation spans by their prefix with profiling-bundle's span lists, for example to keep them out of the
profile entirely:

```yaml
msstc4symfony_profiling:
    spans:
        blacklist: ['operation ']
```

SLO counting does not depend on profiling: when a span list or an application decision maker rejects the span,
the operation still records its SLO events.

With [tracing-bridge-profiling](https://github.com/msstc4symfony/tracing-bridge-profiling) every recorded operation
span runs in a child span of the current W3C trace: while the operation runs, tracing-bundle's log records carry that
span's `span_id`, and outgoing HTTP requests traced by tracing-bundle become its child spans. A span rejected by a
span list or a decision maker is not recorded and gets no trace span.

## Fail-open

Profiling never changes what the operation returns or throws: its exception is rethrown as the same instance, and
a failure to open, classify or end the span is logged as a warning on the `slo_bundle` channel (a failing logger is
swallowed too) while the operation runs unprofiled. slo-bundle itself is fail-open as well: an unreachable metric
storage changes neither the result nor the span.

## Public API

Only the bundle class `Msstc4Symfony\SloBridgeProfiling\SloBridgeProfilingBundle`; the decorator and its handle are
`@internal` and are wired by the bundle. Inject `Msstc4Symfony\SloBundle\Application\Port\OperationMonitorInterface`
as usual.

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

`composer-ci.json` installs every family package from its GitHub repository, plus the optional logic-bundle.
FrameworkBundle and MonologBundle, used by the kernel tests, are listed in its `require-dev` and are also required
by metrics-bundle (both) and profiling-bundle (FrameworkBundle).

```sh
make install-ci                        # composer-ci.json profile with every CI tool
COMPOSER=composer-ci.json make check   # lint, PHPStan, CS, composer validate/audit, Rector, deptrac
make test                              # unit + integration
```

## License

MIT — see [LICENSE](LICENSE).
