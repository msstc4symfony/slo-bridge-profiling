# Известные ловушки

- **`kernel.build_dir` в тестовом контейнере.** `PhpFileLoader` Symfony 7.4 читает этот параметр,
  8.x — нет; без него интеграционные тесты падают только в ячейке 7.4.
- **Теги для своих интерфейсов** регистрировать в `loadExtension()` через
  `registerForAutoconfiguration()`, а не `#[AutoconfigureTag]` на интерфейсе: атрибут на интерфейсе
  из vendor-пакета приложение не подхватывает.
- **`gh run watch` может вернуть 0 на упавшем прогоне.** Итог проверять через
  `gh run view <id> --json conclusion,jobs`.
- **`actions/checkout` по sha делает shallow-клон**; push из такого клона отклоняется как
  "fetch first" — нужен `fetch-depth: 0`.
- **Пороги Infection** (`infection-min-msi`, `infection-min-covered-msi` в `checks.yml`) = замер минус запас,
  с датой замера (UTC) в комментарии; пересчитывать при заметном изменении тестов.
- **`exec()` портит вывод с путями**: режет по строкам и срезает пробелы в конце строки, поэтому
  `git ls-files -z` через него теряет символы в именах. Читать через `proc_open` целиком. В тестах не
  читать stdout и stderr из двух pipe по очереди (deadlock при заполненном буфере) — stderr писать в файл.
- **Код должен разбираться на PHP 8.2** (bundle-standard v3, профиль `php82`): без типизированных констант
  (`const string X` — 8.3) и без `new X()->m()` / `new X()(...)` (8.4) — только `(new X())->m()`. PHPStan (`phpVersion.min: 80200`) ловит
  типизированную константу (`classConstant.nativeTypeNotSupported`), `new X()->m()` не ловит ни один
  инструмент на 8.4 — только `php -l` в job «minimal» (PHP 8.2) и ячейки PHPUnit на 8.2. Шаблоны
  Rector (`withPhpVersion(PHP_82)`) и CS-Fixer (`new_expression_parentheses`) обратно не поднимают.
  `#[Override]` (8.3) допустим: на 8.2 атрибут не инстанцируется. Функции 8.4 (`array_any/all/find`) —
  только с `symfony/polyfill-php84` в `require`.
- **`ProfiledOperationMonitor` не реализует `ResetInterface`**: это безопасно, пока slo-bundle публикует
  `OperationMonitorInterface` алиасом на `OperationMonitor` с тегом `kernel.reset` — тег остаётся на внутреннем
  сервисе (`ContainerWiringTest::testKernelResetStaysOnTheInnerMonitor`). Если slo-bundle сделает интерфейс
  самостоятельным сервисом с тегом, декоратору понадобится `ResetInterface` с делегированием.
- **Kernel-тест с разными конфигурациями**: `TestKernel` принимает `$spanBlacklist` / `$unreachableStorage`;
  каталог кеша включает хеш этих флагов, иначе второй `boot()` в том же процессе возьмёт закешированный контейнер
  с другой конфигурацией.
- **`monolog.logger.slo_bundle` под metrics-bundle — не `Monolog\Logger`**, а `HandlerDecorator` (счётчик ошибок
  metrics-bundle). Канал проверять по записи в `TestHandler` (`monolog.handler.main`), а не через `instanceof Logger`.
- **PHPStan `deadCode.unreachable` после `measure(fn (): never => throw …)`**: шаблон возврата `measure()` выводит
  `never`, и `self::fail()` после вызова — недостижимый код. Ловить исключение в переменную и проверять после `try`.
- **Infection**: `SloBridgeProfilingBundle::build()` не вызывает `parent::build()` — в 7.4 и 8.x он пустой, а вызов
  давал эквивалентный мутант. Замер 2026-11-22 UTC — MSI 100 % (57/57), пороги 97/97.
