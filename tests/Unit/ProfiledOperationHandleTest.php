<?php

declare(strict_types=1);

namespace Msstc4Symfony\SloBridgeProfiling\Test\Unit;

use LogicException;
use Msstc4Symfony\ProfilingBundle\Application\Span\Span;
use Msstc4Symfony\SloBridgeProfiling\Infrastructure\Profiling\ProfiledOperationHandle;
use Msstc4Symfony\SloBridgeProfiling\Infrastructure\Profiling\SpanEndContext;
use Msstc4Symfony\SloBridgeProfiling\Test\Fixtures\CallLog;
use Msstc4Symfony\SloBridgeProfiling\Test\Fixtures\RecordingOperationHandle;
use Msstc4Symfony\SloBridgeProfiling\Test\Fixtures\RecordingProfilingFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(ProfiledOperationHandle::class)]
#[CoversClass(SpanEndContext::class)]
final class ProfiledOperationHandleTest extends TestCase
{
    public function testEndsTheSpanOnceWithTheFirstOutcome(): void
    {
        $log = new CallLog();
        $inner = new RecordingOperationHandle($log);
        $profiling = new RecordingProfilingFactory();
        $handle = new ProfiledOperationHandle($inner, new Span('operation payment.validation'), $profiling);
        $e = new LogicException('refused');
        $later = new RuntimeException('later');

        $handle->failExpected($e);
        $handle->fail($later);
        $handle->end();
        $handle->end();

        self::assertSame(['inner.failExpected', 'inner.fail', 'inner.end', 'inner.end'], $log->calls);
        self::assertSame([$e, $later], $inner->reasons);
        self::assertSame([['outcome' => 'expected_failure', 'exception' => $e::class]], $profiling->endContexts);
    }

    public function testWithoutOutcomeTheSpanEndsAsSuccess(): void
    {
        $profiling = new RecordingProfilingFactory();
        $handle = new ProfiledOperationHandle(new RecordingOperationHandle(), new Span('operation payment.validation'), $profiling);

        $handle->end();

        self::assertSame([['outcome' => 'success']], $profiling->endContexts);
    }

    public function testSucceedWinsOverALaterFailure(): void
    {
        $log = new CallLog();
        $profiling = new RecordingProfilingFactory();
        $handle = new ProfiledOperationHandle(new RecordingOperationHandle($log), new Span('operation payment.validation'), $profiling);

        $handle->succeed();
        $handle->fail(new RuntimeException('late'));
        $handle->end();

        self::assertSame(['inner.succeed', 'inner.fail', 'inner.end'], $log->calls);
        self::assertSame([['outcome' => 'success']], $profiling->endContexts);
    }

    public function testFailWithoutReasonEndsAsErrorWithoutExceptionKey(): void
    {
        $profiling = new RecordingProfilingFactory();
        $handle = new ProfiledOperationHandle(new RecordingOperationHandle(), new Span('operation payment.validation'), $profiling);

        $handle->fail();
        $handle->failExpected(new RuntimeException('late'));
        $handle->end();

        self::assertSame([['outcome' => 'error']], $profiling->endContexts);
    }

    public function testInnerEndRunsBeforeTheSpanEnds(): void
    {
        $log = new CallLog();
        $handle = new ProfiledOperationHandle(new RecordingOperationHandle($log), new Span('operation payment.validation'), new RecordingProfilingFactory($log));

        $handle->end();

        self::assertSame(['inner.end', 'span.end'], $log->calls);
    }

    public function testAThrowingEndSpanDoesNotEscape(): void
    {
        $log = new CallLog();
        $handle = new ProfiledOperationHandle(new RecordingOperationHandle($log), new Span('operation payment.validation'), new RecordingProfilingFactory($log, failOnEnd: true));

        $handle->end();
        $handle->end();

        self::assertSame(['inner.end', 'span.end', 'inner.end'], $log->calls);
    }

    public function testTheSpanEndsEvenWhenTheInnerEndThrows(): void
    {
        $log = new CallLog();
        $handle = new ProfiledOperationHandle(new RecordingOperationHandle($log, failOnEnd: true), new Span('operation payment.validation'), new RecordingProfilingFactory($log));

        $caught = null;
        try {
            $handle->end();
        } catch (RuntimeException $e) {
            $caught = $e;
        }

        self::assertSame('inner end failed', $caught?->getMessage());
        self::assertSame(['inner.end', 'span.end'], $log->calls);
    }
}
