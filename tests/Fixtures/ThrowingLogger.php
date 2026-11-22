<?php

declare(strict_types=1);

namespace Msstc4Symfony\SloBridgeProfiling\Test\Fixtures;

use Override;
use Psr\Log\AbstractLogger;
use RuntimeException;
use Stringable;

final class ThrowingLogger extends AbstractLogger
{
    /**
     * @param array<array-key, mixed> $context
     */
    #[Override]
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        throw new RuntimeException('logger failed');
    }
}
