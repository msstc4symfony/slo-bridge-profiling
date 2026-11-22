# Соглашения

- Use-case — каталог `src/Application/<Aggregate>/<UseCase>/` с `Action` (`final readonly`, `__invoke(Input): Output`),
  `Input` (инварианты в конструкторе) и `Output`.
- Тесты: `tests/Unit/<путь класса>` — рукописные фейки из `tests/Fixtures` вместо PHPUnit-стабов;
  `tests/Integration/ContainerWiringTest` — голый `ContainerBuilder` со стендами (нужны `kernel.environment` и
  `kernel.build_dir` для 7.4); `tests/Integration/KernelBridgeTest` — реальный `TestKernel` (Framework, Monolog,
  Metrics, Slo, Profiling, logic-bundle при наличии, мост), спаны через `RecordingEndProcessor`, SLO-события через
  `RecordingMetricRecorder`. Пропускается только тест, которому нужен logic-bundle.
- Файлы инструментов байт-в-байт из `bundle-standard/templates`; менять их можно только в стандарте. Исключения:
  `phpstan.dist.neon` — свой `level:` (здесь 10, в шаблоне 9); `deptrac.yaml` — значение `BundleRoot` и
  комментарии (сравнивается разобранный YAML), ruleset можно сузить.
- Коммиты — одна строка на английском, ≤ 20 слов; теги `vX.Y.Z`.
