<?php

declare(strict_types=1);

namespace Msstc4Symfony\SloBridgeProfiling\Test\Fixtures;

use DomainException;
use Msstc4Symfony\SloBundle\Application\Port\ExpectedFailureClassifierInterface;
use Override;
use Throwable;

/**
 * An application-level classifier: works without logic-bundle.
 */
final class DomainExceptionClassifier implements ExpectedFailureClassifierInterface
{
    #[Override]
    public function isExpectedFailure(Throwable $e): bool
    {
        return $e instanceof DomainException;
    }
}
