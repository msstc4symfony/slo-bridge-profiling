# Архитектура

- `SloBridgeProfilingBundle` — `AbstractBundle` без дерева конфигурации: `build()` добавляет `RequireBundlesPass`
  (`TYPE_BEFORE_OPTIMIZATION`, приоритет 10), `loadExtension()` импортирует `src/Resources/config/services.php`.
- `ProfiledOperationMonitor` декорирует `OperationMonitorInterface` (приоритет 0, `.inner`); спан `operation <name>`
  открывается только для объявленных имён (определение или конфликт в `SloCatalogInterface`), end context
  `['outcome' => …, 'exception' => get_debug_type()]`. `$logger` автовайрится (тег `monolog.logger`, канал
  `slo_bundle`): `service('logger')->ignoreOnInvalid()` передал бы `null` в типизированный параметр.
- Сервисы бандла приватные, id = FQCN; потребитель получает их автовайрингом.
- Слои (`deptrac.yaml`) — копия `bundle-standard/templates/deptrac.yaml` v3 с полным каноническим ruleset
  (Domain ← DomainService ← Application ← Infrastructure / Presentation / Framework; DependencyInjection — все слои,
  кроме BundleRoot; BundleRoot — Application и DependencyInjection); `BundleRoot` = `^…\\SloBridgeProfilingBundle$`
  (`bin/init` переименовывает токен, якоря остаются). `verify-standard` v3 сверяет его с шаблоном.
- `#[Override]` на всех переопределениях — PHPStan `checkMissingOverrideMethodAttribute` из шаблона.
  Domain/DomainService различаются подкаталогами `src/Domain/` (данные vs операции).
