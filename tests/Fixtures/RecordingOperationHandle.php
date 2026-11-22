<?php

declare(strict_types=1);

namespace Msstc4Symfony\SloBridgeProfiling\Test\Fixtures;

use Msstc4Symfony\SloBundle\Application\Port\OperationHandleInterface;
use Override;
use RuntimeException;
use Throwable;

final class RecordingOperationHandle implements OperationHandleInterface
{
    /**
     * @var list<?Throwable>
     */
    public array $reasons = [];

    public function __construct(
        private readonly CallLog $log = new CallLog(),
        private readonly bool $failOnEnd = false,
    ) {
    }

    #[Override]
    public function succeed(): void
    {
        $this->log->add('inner.succeed');
    }

    #[Override]
    public function failExpected(?Throwable $reason = null): void
    {
        $this->log->add('inner.failExpected');
        $this->reasons[] = $reason;
    }

    #[Override]
    public function fail(?Throwable $reason = null): void
    {
        $this->log->add('inner.fail');
        $this->reasons[] = $reason;
    }

    #[Override]
    public function end(): void
    {
        $this->log->add('inner.end');
        if ($this->failOnEnd) {
            throw new RuntimeException('inner end failed');
        }
    }
}
