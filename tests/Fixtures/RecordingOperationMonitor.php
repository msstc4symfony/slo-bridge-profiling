<?php

declare(strict_types=1);

namespace Msstc4Symfony\SloBridgeProfiling\Test\Fixtures;

use Closure;
use Msstc4Symfony\SloBundle\Application\Port\OperationHandleInterface;
use Msstc4Symfony\SloBundle\Application\Port\OperationMonitorInterface;
use Override;

final class RecordingOperationMonitor implements OperationMonitorInterface
{
    /**
     * @var list<string>
     */
    public array $calls = [];

    public function __construct(private readonly OperationHandleInterface $handle = new RecordingOperationHandle())
    {
    }

    #[Override]
    public function start(string $name): OperationHandleInterface
    {
        $this->calls[] = 'start ' . $name;

        return $this->handle;
    }

    #[Override]
    public function measure(string $name, Closure $operation): mixed
    {
        $this->calls[] = 'measure ' . $name;

        return $operation();
    }
}
