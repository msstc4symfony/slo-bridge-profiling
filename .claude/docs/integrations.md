# Интеграции

- **slo-bundle** (`^1.0`): декорируется `OperationMonitorInterface` (алиас на `OperationMonitor`, тег
  `kernel.reset` остаётся на внутреннем сервисе). Читаются `SloCatalogInterface::forPoint()` /
  `conflictedNamesFor()` (объявлено ли имя — то же правило, что в `OperationMonitor::start()`) и публичный с 1.0
  `ExpectedFailureClassifierInterface` (классификация как в `measure()`; адаптер выбирает slo-bundle по наличию
  logic-bundle: `ActionOutcome` → `ExpectedFailureInterface` → null-классификатор). Приложение может переопределить
  алиас своим классификатором — мост и slo-bundle используют один и тот же сервис
  (`KernelBridgeTest::testExpectedFailureOfAnApplicationClassifierIsTheSameInSpanAndSlo`, флаг `appClassifier`).
- **profiling-bundle** (`^2.3.1`): `ProfilingFactoryInterface::createSpan()` / `endSpan()`. Решение «писать ли
  спан» принимают decision makers profiling-bundle (`spans.whitelist|blacklist` — префиксы, `ListBasedDecisionMaker`
  с приоритетом -1024); SLO-события от них не зависят
  (`KernelBridgeTest::testSloEventsDoNotDependOnProfilingDecisions`). Вложенная операция — дочерний спан.
- **Monolog**: тег `monolog.logger` channel `slo_bundle` на декораторе; `LoggerChannelPass` подставляет
  `monolog.logger.slo_bundle` в автовайренный `$logger`
  (`KernelBridgeTest::testAProfilingFailureIsLoggedOnTheSloBundleChannel`: `ThrowingCreateSpanFactory` декорирует
  `ProfilingFactoryInterface`, предупреждение попадает в `TestHandler` с каналом `slo_bundle`).
  С metrics-bundle этот сервис — `HandlerDecorator` metrics-bundle вокруг `Monolog\Logger`, не сам `Logger`.
- **metrics-bundle**: SLO-события пишет slo-bundle (`SloMetric::EVENTS`, метки `[slo, objective, revision, result]`);
  мост сам метрик не пишет. В kernel-тесте `MetricRecorderInterface` переалиасен на `RecordingMetricRecorder`
  / `ThrowingMetricRecorder`.
- **metrics-bridge-profiling**: превращает каждое `message` спана в серию
  `symfony_profiling_span_duration_histogram_seconds{message}` — поэтому спаны только для объявленных имён.
- **tracing-bridge-profiling** (suggest): каждый *записанный* спан (кроме unit-префиксов `request `, `cli command `,
  `message `, и `action ` при tracing-bridge-logic) получает дочерний trace span в `TraceContextHolder`; логи
  (`TraceLogProcessor`) несут его `span_id`, исходящие HTTP-запросы — его дочерние спаны. Отклонённый decision
  maker'ом спан trace span не получает.
