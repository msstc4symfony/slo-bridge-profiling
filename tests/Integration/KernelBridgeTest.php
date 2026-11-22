<?php

declare(strict_types=1);

namespace Msstc4Symfony\SloBridgeProfiling\Test\Integration;

use Monolog\Handler\TestHandler;
use Monolog\LogRecord;
use Msstc4Symfony\LogicBundle\Application\Action\ExpectedFailureInterface;
use Msstc4Symfony\SloBridgeProfiling\Infrastructure\Profiling\ProfiledOperationMonitor;
use Msstc4Symfony\SloBridgeProfiling\Test\Fixtures\RecordingEndProcessor;
use Msstc4Symfony\SloBridgeProfiling\Test\Fixtures\RecordingMetricRecorder;
use Msstc4Symfony\SloBridgeProfiling\Test\Fixtures\RejectedValidation;
use Msstc4Symfony\SloBridgeProfiling\Test\Integration\Kernel\TestKernel;
use Msstc4Symfony\SloBundle\Application\Port\OperationMonitorInterface;
use Msstc4Symfony\SloBundle\Infrastructure\MetricsBundle\SloMetric;
use Override;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;

#[CoversNothing]
final class KernelBridgeTest extends TestCase
{
    private const SPAN = 'operation payment.validation';

    private const GOOD_EVENTS = [['payment.validation', 'availability', 'good'], ['payment.validation', 'latency', 'good']];

    private ?TestKernel $kernel = null;

    /** @var list<string> */
    private array $bundleDeprecations = [];

    #[Override]
    protected function setUp(): void
    {
        // trigger_deprecation() silences its notice with @, which PHPUnit ignores; record it directly.
        set_error_handler(function (int $level, string $message): bool {
            if (str_contains($message, 'msstc4symfony/')) {
                $this->bundleDeprecations[] = $message;
            }

            return false;
        }, E_USER_DEPRECATED);
        (new Filesystem())->remove(TestKernel::cacheRoot());
    }

    #[Override]
    protected function tearDown(): void
    {
        restore_error_handler();
        $this->kernel?->shutdown();
        (new Filesystem())->remove(TestKernel::cacheRoot());
    }

    public function testTheMonitorServiceIsTheProfiledDecorator(): void
    {
        self::assertInstanceOf(ProfiledOperationMonitor::class, $this->monitor());
        self::assertSame([], $this->bundleDeprecations);
    }

    public function testTheDecoratorLogsOnTheSloBundleChannel(): void
    {
        $logger = (new ReflectionProperty(ProfiledOperationMonitor::class, 'logger'))->getValue($this->monitor());
        self::assertInstanceOf(LoggerInterface::class, $logger);
        self::assertSame($this->container()->get('monolog.logger.slo_bundle'), $logger);

        $logger->warning('probe');

        $handler = $this->container()->get('monolog.handler.main');
        self::assertInstanceOf(TestHandler::class, $handler);
        self::assertSame(['slo_bundle'], array_map(static fn (LogRecord $record): string => $record->channel, $handler->getRecords()));
    }

    public function testMeasureRecordsTheSloEventsAndOneOperationSpan(): void
    {
        self::assertSame(42, $this->monitor()->measure('payment.validation', static fn (): int => 42));

        self::assertSame([$this->endedSpan(self::SPAN, null, ['outcome' => 'success'])], $this->spans());
        self::assertSame(self::GOOD_EVENTS, $this->sloEvents());
    }

    public function testStartAndEndRecordOneSpanAndTheSameEvents(): void
    {
        $handle = $this->monitor()->start('payment.validation');
        self::assertSame([], $this->spans());
        $handle->succeed();
        $handle->end();

        self::assertSame([$this->endedSpan(self::SPAN, null, ['outcome' => 'success'])], $this->spans());
        self::assertSame(self::GOOD_EVENTS, $this->sloEvents());
    }

    public function testSloEventsDoNotDependOnProfilingDecisions(): void
    {
        $this->boot(spanBlacklist: [ProfiledOperationMonitor::SPAN_PREFIX]);

        self::assertSame(42, $this->monitor()->measure('payment.validation', static fn (): int => 42));

        self::assertSame([], $this->spans());
        self::assertSame(self::GOOD_EVENTS, $this->sloEvents());
    }

    public function testAnUndeclaredOperationOpensNoSpanAndRecordsNothing(): void
    {
        self::assertSame(42, $this->monitor()->measure('unknown.op', static fn (): int => 42));

        self::assertSame([], $this->spans());
        self::assertSame([], $this->sloEvents());
    }

    public function testANestedOperationIsAChildSpan(): void
    {
        $monitor = $this->monitor();
        $result = $monitor->measure('payment.validation', static fn (): int => $monitor->measure('payment.capture', static fn (): int => 42));

        self::assertSame(42, $result);
        self::assertSame([
            $this->endedSpan('operation payment.capture', self::SPAN, ['outcome' => 'success']),
            $this->endedSpan(self::SPAN, null, ['outcome' => 'success']),
        ], $this->spans());
    }

    public function testExpectedFailureIsTheSameInSpanAndSlo(): void
    {
        if (!interface_exists(ExpectedFailureInterface::class)) {
            self::markTestSkipped('Expected failures are classified through logic-bundle.');
        }

        $rejection = new RejectedValidation('Card declined.');
        $caught = null;
        try {
            $this->monitor()->measure('payment.validation', static fn (): never => throw $rejection);
        } catch (RejectedValidation $e) {
            $caught = $e;
        }

        self::assertSame($rejection, $caught);
        self::assertSame([$this->endedSpan(self::SPAN, null, ['outcome' => 'expected_failure', 'exception' => RejectedValidation::class])], $this->spans());
        self::assertSame(self::GOOD_EVENTS, $this->sloEvents());
    }

    public function testUnreachableStorageDoesNotChangeTheResult(): void
    {
        $this->boot(unreachableStorage: true);

        self::assertSame(42, $this->monitor()->measure('payment.validation', static fn (): int => 42));

        self::assertSame([$this->endedSpan(self::SPAN, null, ['outcome' => 'success'])], $this->spans());
    }

    /**
     * @param array<string, string> $context
     *
     * @return array{message: string, parent: ?string, context: array<string, string>}
     */
    private function endedSpan(string $message, ?string $parent, array $context): array
    {
        return ['message' => $message, 'parent' => $parent, 'context' => $context];
    }

    /**
     * @param list<string>|null $spanBlacklist
     */
    private function boot(?array $spanBlacklist = null, bool $unreachableStorage = false): void
    {
        $this->kernel?->shutdown();
        $this->kernel = new TestKernel('test', false, $spanBlacklist, $unreachableStorage);
        $this->kernel->boot();
    }

    private function container(): ContainerInterface
    {
        if (!$this->kernel instanceof TestKernel) {
            $this->boot();
        }

        self::assertInstanceOf(TestKernel::class, $this->kernel);
        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);

        return $container;
    }

    private function monitor(): OperationMonitorInterface
    {
        $monitor = $this->container()->get(OperationMonitorInterface::class);
        self::assertInstanceOf(OperationMonitorInterface::class, $monitor);

        return $monitor;
    }

    /**
     * @return list<array{message: string, parent: ?string, context: array<string, mixed>}>
     */
    private function spans(): array
    {
        $recorder = $this->container()->get(RecordingEndProcessor::class);
        self::assertInstanceOf(RecordingEndProcessor::class, $recorder);

        return $recorder->ended();
    }

    /**
     * [slo, objective, result] of every SLO event, sorted.
     *
     * @return list<array{string, string, string}>
     */
    private function sloEvents(): array
    {
        $recorder = $this->container()->get(RecordingMetricRecorder::class);
        self::assertInstanceOf(RecordingMetricRecorder::class, $recorder);

        $events = [];
        foreach ($recorder->calls as $call) {
            if ($call['method'] !== 'increment' || $call['metric'] !== SloMetric::EVENTS) {
                continue;
            }

            [$slo, $objective, $revision, $result] = array_map(strval(...), $call['labels']);
            self::assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $revision);
            $events[] = [$slo, $objective, $result];
        }
        sort($events);

        return $events;
    }
}
