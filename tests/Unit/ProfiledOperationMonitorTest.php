<?php

declare(strict_types=1);

namespace Msstc4Symfony\SloBridgeProfiling\Test\Unit;

use Msstc4Symfony\SloBridgeProfiling\Infrastructure\Profiling\ProfiledOperationHandle;
use Msstc4Symfony\SloBridgeProfiling\Infrastructure\Profiling\ProfiledOperationMonitor;
use Msstc4Symfony\SloBridgeProfiling\Infrastructure\Profiling\SpanEndContext;
use Msstc4Symfony\SloBridgeProfiling\Test\Fixtures\RecordingLogger;
use Msstc4Symfony\SloBridgeProfiling\Test\Fixtures\RecordingOperationHandle;
use Msstc4Symfony\SloBridgeProfiling\Test\Fixtures\RecordingOperationMonitor;
use Msstc4Symfony\SloBridgeProfiling\Test\Fixtures\RecordingProfilingFactory;
use Msstc4Symfony\SloBridgeProfiling\Test\Fixtures\StubClassifier;
use Msstc4Symfony\SloBridgeProfiling\Test\Fixtures\StubSloCatalog;
use Msstc4Symfony\SloBridgeProfiling\Test\Fixtures\ThrowingLogger;
use Msstc4Symfony\SloBundle\Domain\Repository\SloCatalogInterface;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use RuntimeException;
use Throwable;

#[CoversClass(ProfiledOperationMonitor::class)]
#[CoversClass(SpanEndContext::class)]
#[UsesClass(ProfiledOperationHandle::class)]
final class ProfiledOperationMonitorTest extends TestCase
{
    private const NAME = StubSloCatalog::DECLARED;

    private RecordingOperationMonitor $inner;

    private RecordingProfilingFactory $profiling;

    private RecordingLogger $logger;

    #[Override]
    protected function setUp(): void
    {
        $this->inner = new RecordingOperationMonitor();
        $this->profiling = new RecordingProfilingFactory();
        $this->logger = new RecordingLogger();
    }

    public function testMeasureOpensAnOperationSpanAndEndsItWithSuccess(): void
    {
        $result = $this->monitor()->measure(self::NAME, static fn (): int => 42);

        self::assertSame(42, $result);
        self::assertSame(['operation payment.validation'], $this->profiling->messages);
        self::assertSame([['outcome' => 'success']], $this->profiling->endContexts);
        self::assertSame(['measure payment.validation'], $this->inner->calls);
        self::assertSame([], $this->logger->records);
    }

    public function testMeasureEndsTheSpanWithErrorAndRethrowsTheSameException(): void
    {
        $failure = new RuntimeException('boom');

        self::assertSame($failure, $this->caught(fn (): mixed => $this->monitor()->measure(self::NAME, static fn (): never => throw $failure)));
        self::assertSame([['outcome' => 'error', 'exception' => RuntimeException::class]], $this->profiling->endContexts);
    }

    public function testMeasureUsesTheSharedClassifierForExpectedFailures(): void
    {
        $failure = new RuntimeException('refused');

        self::assertSame($failure, $this->caught(fn (): mixed => $this->monitor(classifier: new StubClassifier(expected: true))->measure(self::NAME, static fn (): never => throw $failure)));
        self::assertSame([['outcome' => 'expected_failure', 'exception' => RuntimeException::class]], $this->profiling->endContexts);
    }

    public function testTheExceptionKeyCarriesNoAnonymousClassName(): void
    {
        $failure = new class('boom') extends RuntimeException {};

        $this->caught(fn (): mixed => $this->monitor()->measure(self::NAME, static fn (): never => throw $failure));

        self::assertSame([['outcome' => 'error', 'exception' => 'RuntimeException@anonymous']], $this->profiling->endContexts);
    }

    public function testAThrowingClassifierCountsAsError(): void
    {
        $failure = new RuntimeException('boom');

        self::assertSame($failure, $this->caught(fn (): mixed => $this->monitor(classifier: new StubClassifier(fail: true))->measure(self::NAME, static fn (): never => throw $failure)));
        self::assertSame([['outcome' => 'error', 'exception' => RuntimeException::class]], $this->profiling->endContexts);
        $this->assertOneWarning('SLO operation exception could not be classified, it counts as an error.');
    }

    public function testUndeclaredNameOpensNoSpan(): void
    {
        $result = $this->monitor(new StubSloCatalog())->measure(self::NAME, static fn (): int => 42);

        self::assertSame(42, $result);
        self::assertSame([], $this->profiling->messages);
        self::assertSame([], $this->profiling->endContexts);
        self::assertSame(['measure payment.validation'], $this->inner->calls);
        self::assertSame([], $this->logger->records);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidNames(): iterable
    {
        yield 'empty' => [''];
        yield 'not a name' => ['Bad Name'];
    }

    #[DataProvider('invalidNames')]
    public function testInvalidNameOpensNoSpanAndDoesNotThrow(string $name): void
    {
        $result = $this->monitor()->measure($name, static fn (): int => 42);

        self::assertSame(42, $result);
        self::assertSame([], $this->profiling->messages);
        self::assertSame(['measure ' . $name], $this->inner->calls);
        // The inner OperationMonitor already warns about invalid names.
        self::assertSame([], $this->logger->records);
    }

    public function testAConflictedNameStillOpensASpan(): void
    {
        $this->monitor(new StubSloCatalog(conflicted: ['payment']))->measure(self::NAME, static fn (): int => 42);

        self::assertSame(['operation payment.validation'], $this->profiling->messages);
    }

    public function testAThrowingCreateSpanDoesNotBreakMeasure(): void
    {
        $this->profiling = new RecordingProfilingFactory(failOnCreate: true);

        self::assertSame(42, $this->monitor()->measure(self::NAME, static fn (): int => 42));
        self::assertSame(['measure payment.validation'], $this->inner->calls);
        self::assertSame([], $this->profiling->endContexts);
        $this->assertOneWarning('SLO operation span could not be opened, the operation is not profiled.');
    }

    public function testAThrowingCatalogDoesNotBreakMeasure(): void
    {
        self::assertSame(42, $this->monitor(new StubSloCatalog(fail: true))->measure(self::NAME, static fn (): int => 42));
        self::assertSame(['measure payment.validation'], $this->inner->calls);
        self::assertSame([], $this->profiling->messages);
        $this->assertOneWarning('SLO operation span could not be opened, the operation is not profiled.');
    }

    public function testAThrowingEndSpanKeepsTheResult(): void
    {
        $this->profiling = new RecordingProfilingFactory(failOnEnd: true);

        self::assertSame(42, $this->monitor()->measure(self::NAME, static fn (): int => 42));
        $this->assertOneWarning('SLO operation span could not be ended.');
    }

    public function testAThrowingEndSpanKeepsTheOperationException(): void
    {
        $this->profiling = new RecordingProfilingFactory(failOnEnd: true);
        $failure = new RuntimeException('boom');

        self::assertSame($failure, $this->caught(fn (): mixed => $this->monitor()->measure(self::NAME, static fn (): never => throw $failure)));
    }

    public function testAFailingLoggerIsSwallowed(): void
    {
        $this->profiling = new RecordingProfilingFactory(failOnCreate: true);
        $monitor = new ProfiledOperationMonitor($this->inner, $this->profiling, StubSloCatalog::declaring(), new StubClassifier(fail: true), new ThrowingLogger());
        $failure = new RuntimeException('boom');

        self::assertSame(42, $monitor->measure(self::NAME, static fn (): int => 42));
        self::assertSame($failure, $this->caught(static fn (): mixed => $monitor->measure(self::NAME, static fn (): never => throw $failure)));
    }

    public function testWithoutALoggerFailuresAreSilent(): void
    {
        $this->profiling = new RecordingProfilingFactory(failOnCreate: true);
        $monitor = new ProfiledOperationMonitor($this->inner, $this->profiling, StubSloCatalog::declaring(), new StubClassifier());

        self::assertSame(42, $monitor->measure(self::NAME, static fn (): int => 42));
    }

    public function testStartWrapsTheInnerHandleAndOpensASpan(): void
    {
        $handle = $this->monitor()->start(self::NAME);

        self::assertInstanceOf(ProfiledOperationHandle::class, $handle);
        self::assertSame(['start payment.validation'], $this->inner->calls);
        self::assertSame(['operation payment.validation'], $this->profiling->messages);
        self::assertSame([], $this->profiling->endContexts);

        $handle->end();

        self::assertSame([['outcome' => 'success']], $this->profiling->endContexts);
    }

    public function testStartForAnUndeclaredNameReturnsTheInnerHandle(): void
    {
        $innerHandle = new RecordingOperationHandle();
        $this->inner = new RecordingOperationMonitor($innerHandle);

        self::assertSame($innerHandle, $this->monitor(new StubSloCatalog())->start('unknown'));
        self::assertSame([], $this->profiling->messages);
    }

    public function testStartReturnsTheInnerHandleWhenTheSpanCannotBeOpened(): void
    {
        $innerHandle = new RecordingOperationHandle();
        $this->inner = new RecordingOperationMonitor($innerHandle);
        $this->profiling = new RecordingProfilingFactory(failOnCreate: true);

        self::assertSame($innerHandle, $this->monitor()->start(self::NAME));
        $this->assertOneWarning('SLO operation span could not be opened, the operation is not profiled.');
    }

    public function testStartForAnInvalidNameReturnsTheInnerHandle(): void
    {
        $innerHandle = new RecordingOperationHandle();
        $this->inner = new RecordingOperationMonitor($innerHandle);

        self::assertSame($innerHandle, $this->monitor()->start('Bad Name'));
        self::assertSame([], $this->logger->records);
    }

    private function monitor(?SloCatalogInterface $catalog = null, StubClassifier $classifier = new StubClassifier(), ?LoggerInterface $logger = null): ProfiledOperationMonitor
    {
        return new ProfiledOperationMonitor($this->inner, $this->profiling, $catalog ?? StubSloCatalog::declaring(), $classifier, $logger ?? $this->logger);
    }

    /**
     * @param callable(): mixed $call
     */
    private function caught(callable $call): ?Throwable
    {
        try {
            $call();
        } catch (Throwable $e) {
            return $e;
        }

        return null;
    }

    private function assertOneWarning(string $message): void
    {
        self::assertCount(1, $this->logger->records);
        self::assertSame(LogLevel::WARNING, $this->logger->records[0]['level']);
        self::assertSame($message, $this->logger->records[0]['message']);
        self::assertInstanceOf(Throwable::class, $this->logger->records[0]['context']['exception'] ?? null);
    }
}
