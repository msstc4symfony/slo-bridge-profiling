<?php

declare(strict_types=1);

namespace Msstc4Symfony\SloBridgeProfiling\Test\Fixtures;

use Msstc4Symfony\ProfilingBundle\Application\ProfilingFactoryInterface;
use Msstc4Symfony\ProfilingBundle\Application\Span\Span;
use Msstc4Symfony\ProfilingBundle\Application\Span\SpanInterface;
use Override;
use RuntimeException;

final class RecordingProfilingFactory implements ProfilingFactoryInterface
{
    /**
     * @var list<string>
     */
    public array $messages = [];

    /**
     * @var list<array<string, mixed>>
     */
    public array $endContexts = [];

    public function __construct(
        private readonly CallLog $log = new CallLog(),
        private readonly bool $failOnCreate = false,
        private readonly bool $failOnEnd = false,
    ) {
    }

    #[Override]
    public function createSpan(string $message, array $context = []): SpanInterface
    {
        $this->log->add('span.create');
        if ($this->failOnCreate) {
            throw new RuntimeException('createSpan failed');
        }
        $this->messages[] = $message;

        return new Span($message, $context);
    }

    #[Override]
    public function endSpan(SpanInterface $span, array $context = []): void
    {
        $this->log->add('span.end');
        $this->endContexts[] = $context;
        if ($this->failOnEnd) {
            throw new RuntimeException('endSpan failed');
        }
    }

    #[Override]
    public function endAll(): void
    {
    }

    #[Override]
    public function keepOpenOnReset(SpanInterface $span): void
    {
    }
}
