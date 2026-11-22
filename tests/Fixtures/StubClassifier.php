<?php

declare(strict_types=1);

namespace Msstc4Symfony\SloBridgeProfiling\Test\Fixtures;

use Msstc4Symfony\SloBundle\Application\Port\ExpectedFailureClassifierInterface;
use Override;
use RuntimeException;
use Throwable;

final readonly class StubClassifier implements ExpectedFailureClassifierInterface
{
    public function __construct(
        private bool $expected = false,
        private bool $fail = false,
    ) {
    }

    #[Override]
    public function isExpectedFailure(Throwable $e): bool
    {
        if ($this->fail) {
            throw new RuntimeException('classifier failed');
        }

        return $this->expected;
    }
}
