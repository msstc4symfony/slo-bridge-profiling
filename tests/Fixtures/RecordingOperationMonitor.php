<?php

declare(strict_types=1);

namespace Msstc4Symfony\SloBridgeProfiling\Test\Fixtures;

use Closure;
use Msstc4Symfony\SloBundle\Application\Port\OperationHandleInterface;
use Msstc4Symfony\SloBundle\Application\Port\OperationMonitorInterface;
use Override;
use RuntimeException;

final class RecordingOperationMonitor implements OperationMonitorInterface
{
    /**
     * @var list<string>
     */
    public array $calls = [];

    public function __construct(
        private readonly OperationHandleInterface $handle = new RecordingOperationHandle(),
        private readonly CallLog $log = new CallLog(),
        private readonly bool $failOnStart = false,
    ) {
    }

    #[Override]
    public function start(string $name): OperationHandleInterface
    {
        $this->calls[] = 'start ' . $name;
        $this->log->add('inner.start');
        if ($this->failOnStart) {
            throw new RuntimeException('inner start failed');
        }

        return $this->handle;
    }

    #[Override]
    public function measure(string $name, Closure $operation): mixed
    {
        $this->calls[] = 'measure ' . $name;
        $this->log->add('inner.measure');

        return $operation();
    }
}
