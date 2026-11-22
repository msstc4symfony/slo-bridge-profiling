<?php

declare(strict_types=1);

namespace Msstc4Symfony\SloBridgeProfiling\Test\Fixtures;

use Msstc4Symfony\LogicBundle\Application\Action\ExpectedFailureInterface;
use RuntimeException;

final class RejectedValidation extends RuntimeException implements ExpectedFailureInterface
{
}
