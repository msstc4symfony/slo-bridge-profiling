<?php

declare(strict_types=1);

namespace Msstc4Symfony\SloBridgeProfiling\Infrastructure\Profiling;

use Msstc4Symfony\SloBundle\Domain\Enum\Outcome;
use Throwable;

/**
 * End context of operation spans, the profiling-bridge-logic convention.
 *
 * @internal
 */
final class SpanEndContext
{
    /**
     * @return array{outcome: value-of<Outcome>, exception?: string}
     */
    public static function of(Outcome $outcome, ?Throwable $reason): array
    {
        $context = ['outcome' => $outcome->value];
        if ($reason instanceof Throwable) {
            // get_debug_type(): an anonymous class name carries a NUL byte and the server path.
            $context['exception'] = get_debug_type($reason);
        }

        return $context;
    }
}
