<?php

declare(strict_types=1);

namespace Msstc4Symfony\SloBridgeProfiling\Test\Fixtures;

use Msstc4Symfony\ProfilingBundle\Application\ProfilingFactoryInterface;
use Msstc4Symfony\ProfilingBundle\Application\Span\SpanInterface;
use Override;
use RuntimeException;

/**
 * A profiling factory whose spans cannot be opened; everything else reaches the real factory.
 */
final readonly class ThrowingCreateSpanFactory implements ProfilingFactoryInterface
{
    public function __construct(
        private ProfilingFactoryInterface $inner,
    ) {
    }

    #[Override]
    public function createSpan(string $message, array $context = []): SpanInterface
    {
        throw new RuntimeException('Spans cannot be opened.');
    }

    #[Override]
    public function endSpan(SpanInterface $span, array $context = []): void
    {
        $this->inner->endSpan($span, $context);
    }

    #[Override]
    public function endAll(): void
    {
        $this->inner->endAll();
    }

    #[Override]
    public function keepOpenOnReset(SpanInterface $span): void
    {
        $this->inner->keepOpenOnReset($span);
    }
}
