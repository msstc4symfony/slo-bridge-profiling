<?php

declare(strict_types=1);

namespace Msstc4Symfony\SloBridgeProfiling\Infrastructure\Profiling;

use Closure;
use Msstc4Symfony\ProfilingBundle\Application\ProfilingFactoryInterface;
use Msstc4Symfony\ProfilingBundle\Application\Span\SpanInterface;
use Msstc4Symfony\SloBundle\Application\Port\ExpectedFailureClassifierInterface;
use Msstc4Symfony\SloBundle\Application\Port\OperationHandleInterface;
use Msstc4Symfony\SloBundle\Application\Port\OperationMonitorInterface;
use Msstc4Symfony\SloBundle\Domain\Enum\Outcome;
use Msstc4Symfony\SloBundle\Domain\Enum\PointType;
use Msstc4Symfony\SloBundle\Domain\Repository\SloCatalogInterface;
use Msstc4Symfony\SloBundle\Domain\ValueObject\PointId;
use Msstc4Symfony\SloBundle\Domain\ValueObject\SloName;
use Override;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * Fail-open: profiling never changes the result or the exception of the operation. Only declared
 * operation names open a span, so span messages stay bounded by the configuration.
 *
 * @internal
 */
final readonly class ProfiledOperationMonitor implements OperationMonitorInterface
{
    // A stable prefix lets msstc4symfony_profiling.spans.whitelist|blacklist select operation spans.
    public const SPAN_PREFIX = 'operation ';

    public function __construct(
        private OperationMonitorInterface $inner,
        private ProfilingFactoryInterface $profiling,
        private SloCatalogInterface $catalog,
        private ExpectedFailureClassifierInterface $classifier,
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * The span opens before the inner handle starts its clock, so opening it never counts as operation latency.
     */
    #[Override]
    public function start(string $name): OperationHandleInterface
    {
        $span = $this->openSpan($name);
        if (!$span instanceof SpanInterface) {
            return $this->inner->start($name);
        }

        try {
            $handle = $this->inner->start($name);
        } catch (Throwable $e) {
            $this->endSpan($name, $span, Outcome::Error, $e);

            throw $e;
        }

        return new ProfiledOperationHandle(
            $handle,
            fn (Outcome $outcome, ?Throwable $reason) => $this->endSpan($name, $span, $outcome, $reason),
        );
    }

    #[Override]
    public function measure(string $name, Closure $operation): mixed
    {
        $span = $this->openSpan($name);
        if (!$span instanceof SpanInterface) {
            return $this->inner->measure($name, $operation);
        }

        $failure = null;
        try {
            return $this->inner->measure($name, $operation);
        } catch (Throwable $e) {
            $failure = $e;

            throw $e;
        } finally {
            $this->endSpan($name, $span, $this->outcome($failure), $failure);
        }
    }

    private function openSpan(string $name): ?SpanInterface
    {
        try {
            $key = SloName::fromString($name)->value;
        } catch (Throwable) {
            // The inner OperationMonitor already warns about invalid names.
            return null;
        }

        try {
            $point = PointId::of(PointType::Operation, $key);
            // Same rule as OperationMonitor::start(): a conflicted name is still a declared operation.
            if ($this->catalog->forPoint($point) === [] && $this->catalog->conflictedNamesFor($point) === []) {
                return null;
            }

            return $this->profiling->createSpan(self::SPAN_PREFIX . $key);
        } catch (Throwable $e) {
            $this->warn('SLO operation span could not be opened, the operation is not profiled.', $name, $e);

            return null;
        }
    }

    private function endSpan(string $name, SpanInterface $span, Outcome $outcome, ?Throwable $reason): void
    {
        try {
            $this->profiling->endSpan($span, SpanEndContext::of($outcome, $reason));
        } catch (Throwable $e) {
            $this->warn('SLO operation span could not be ended.', $name, $e);
        }
    }

    private function outcome(?Throwable $failure): Outcome
    {
        if (!$failure instanceof Throwable) {
            return Outcome::Success;
        }

        try {
            return $this->classifier->isExpectedFailure($failure) ? Outcome::ExpectedFailure : Outcome::Error;
        } catch (Throwable) {
            // The inner OperationMonitor classifies the same exception and already warns about the failure.
            return Outcome::Error;
        }
    }

    /**
     * @param non-empty-literal-string $message
     */
    private function warn(string $message, string $operation, Throwable $exception): void
    {
        try {
            $this->logger->warning($message, ['operation' => $operation, 'exception' => $exception]);
        } catch (Throwable) {
            // A failing logger must not break the operation.
        }
    }
}
