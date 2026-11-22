<?php

declare(strict_types=1);

namespace Msstc4Symfony\SloBridgeProfiling\Test\Unit;

use Closure;
use LogicException;
use Msstc4Symfony\SloBridgeProfiling\Infrastructure\Profiling\ProfiledOperationHandle;
use Msstc4Symfony\SloBridgeProfiling\Test\Fixtures\CallLog;
use Msstc4Symfony\SloBridgeProfiling\Test\Fixtures\RecordingOperationHandle;
use Msstc4Symfony\SloBundle\Domain\Enum\Outcome;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

#[CoversClass(ProfiledOperationHandle::class)]
final class ProfiledOperationHandleTest extends TestCase
{
    private CallLog $log;

    /** @var list<array{Outcome, ?Throwable}> */
    private array $endedWith = [];

    #[Override]
    protected function setUp(): void
    {
        $this->log = new CallLog();
    }

    public function testEndsTheSpanOnceWithTheFirstOutcome(): void
    {
        $inner = new RecordingOperationHandle($this->log);
        $handle = $this->handle($inner);
        $e = new LogicException('refused');
        $later = new RuntimeException('later');

        $handle->failExpected($e);
        $handle->fail($later);
        $handle->end();
        $handle->end();

        self::assertSame(['inner.failExpected', 'inner.fail', 'inner.end', 'span.end', 'inner.end'], $this->log->calls);
        self::assertSame([$e, $later], $inner->reasons);
        self::assertSame([[Outcome::ExpectedFailure, $e]], $this->endedWith);
    }

    public function testWithoutOutcomeTheSpanEndsAsSuccess(): void
    {
        $this->handle()->end();

        self::assertSame([[Outcome::Success, null]], $this->endedWith);
    }

    public function testSucceedWinsOverALaterFailure(): void
    {
        $handle = $this->handle();

        $handle->succeed();
        $handle->fail(new RuntimeException('late'));
        $handle->end();

        self::assertSame(['inner.succeed', 'inner.fail', 'inner.end', 'span.end'], $this->log->calls);
        self::assertSame([[Outcome::Success, null]], $this->endedWith);
    }

    public function testFailWithoutReasonEndsAsError(): void
    {
        $handle = $this->handle();

        $handle->fail();
        $handle->failExpected(new RuntimeException('late'));
        $handle->end();

        self::assertSame([[Outcome::Error, null]], $this->endedWith);
    }

    public function testInnerEndRunsBeforeTheSpanEnds(): void
    {
        $this->handle()->end();

        self::assertSame(['inner.end', 'span.end'], $this->log->calls);
    }

    public function testTheSpanEndsEvenWhenTheInnerEndThrows(): void
    {
        $handle = $this->handle(new RecordingOperationHandle($this->log, failOnEnd: true));

        $caught = null;
        try {
            $handle->end();
        } catch (RuntimeException $e) {
            $caught = $e;
        }

        self::assertSame('inner end failed', $caught?->getMessage());
        self::assertSame(['inner.end', 'span.end'], $this->log->calls);
    }

    /**
     * @return iterable<string, array{Closure(ProfiledOperationHandle): void}>
     */
    public static function outcomeCalls(): iterable
    {
        yield 'succeed' => [static fn (ProfiledOperationHandle $handle) => $handle->succeed()];
        yield 'fail' => [static fn (ProfiledOperationHandle $handle) => $handle->fail(new RuntimeException('boom'))];
        yield 'failExpected' => [static fn (ProfiledOperationHandle $handle) => $handle->failExpected(new RuntimeException('refused'))];
    }

    /**
     * The inner handle must not throw by contract; if it does, the decorator stays transparent and the span keeps
     * the outcome decided before (none here, so success).
     *
     * @param Closure(ProfiledOperationHandle): void $call
     */
    #[DataProvider('outcomeCalls')]
    public function testAThrowingInnerOutcomePropagatesAndLeavesTheSpanOutcomeUndecided(Closure $call): void
    {
        $handle = $this->handle(new RecordingOperationHandle($this->log, failOnDecide: true));

        $caught = null;
        try {
            $call($handle);
        } catch (RuntimeException $e) {
            $caught = $e;
        }
        $handle->end();

        self::assertSame('inner outcome failed', $caught?->getMessage());
        self::assertSame([[Outcome::Success, null]], $this->endedWith);
    }

    public function testAThrowingEndSpanClosureDoesNotEscape(): void
    {
        $handle = new ProfiledOperationHandle(new RecordingOperationHandle($this->log), static function (): never {
            throw new RuntimeException('end span failed');
        });

        $handle->end();
        $handle->end();

        self::assertSame(['inner.end', 'inner.end'], $this->log->calls);
    }

    private function handle(?RecordingOperationHandle $inner = null): ProfiledOperationHandle
    {
        return new ProfiledOperationHandle($inner ?? new RecordingOperationHandle($this->log), function (Outcome $outcome, ?Throwable $reason): void {
            $this->log->add('span.end');
            $this->endedWith[] = [$outcome, $reason];
        });
    }
}
