<?php

declare(strict_types=1);

namespace Msstc4Symfony\SloBridgeProfiling\Infrastructure\Profiling;

use Closure;
use Msstc4Symfony\SloBundle\Application\Port\OperationHandleInterface;
use Msstc4Symfony\SloBundle\Domain\Enum\Outcome;
use Override;
use Throwable;

/**
 * Mirrors the handle contract (first outcome wins, none means success) for the span; every call
 * still reaches the inner handle, which applies the same rules to the SLO event.
 *
 * @internal
 */
final class ProfiledOperationHandle implements OperationHandleInterface
{
    private ?Outcome $outcome = null;

    private ?Throwable $reason = null;

    private bool $ended = false;

    /**
     * @param Closure(Outcome, ?Throwable): void $endSpan ends the span and reports its own failures
     */
    public function __construct(
        private readonly OperationHandleInterface $inner,
        private readonly Closure $endSpan,
    ) {
    }

    #[Override]
    public function succeed(): void
    {
        $this->inner->succeed();
        $this->decide(Outcome::Success, null);
    }

    #[Override]
    public function failExpected(?Throwable $reason = null): void
    {
        $this->inner->failExpected($reason);
        $this->decide(Outcome::ExpectedFailure, $reason);
    }

    #[Override]
    public function fail(?Throwable $reason = null): void
    {
        $this->inner->fail($reason);
        $this->decide(Outcome::Error, $reason);
    }

    /**
     * The SLO event is recorded first, so it never waits on profiling.
     */
    #[Override]
    public function end(): void
    {
        try {
            $this->inner->end();
        } finally {
            if (!$this->ended) {
                $this->ended = true;
                try {
                    ($this->endSpan)($this->outcome ?? Outcome::Success, $this->reason);
                } catch (Throwable) {
                    // The closure reports its own failures; this only guards the operation against a broken one.
                }
            }
        }
    }

    private function decide(Outcome $outcome, ?Throwable $reason): void
    {
        if ($this->outcome instanceof Outcome) {
            return;
        }
        $this->outcome = $outcome;
        $this->reason = $reason;
    }
}
