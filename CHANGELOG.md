# Changelog

## [1.0.0] - 2026-11-22

- Changed: requires slo-bundle ^1.0 (stable).

## [1.0.0-rc.1] - 2026-11-22

- First release candidate: decorates slo-bundle's `OperationMonitorInterface` and opens a profiling-bundle span
  `operation <name>` around every `measure()` / `start()` of a declared SLO operation, ended with the SLO outcome
  (`outcome`, `exception`). Undeclared or invalid names open no span; profiling failures are logged on the
  `slo_bundle` channel and never reach the operation. Requires slo-bundle ^1.0.0-rc.2, profiling-bundle ^2.3.1 and
  metrics-bundle ^2.3.
