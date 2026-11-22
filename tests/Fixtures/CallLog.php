<?php

declare(strict_types=1);

namespace Msstc4Symfony\SloBridgeProfiling\Test\Fixtures;

/**
 * Shared by several fakes to observe the order of their calls.
 */
final class CallLog
{
    /**
     * @var list<non-empty-string>
     */
    public array $calls = [];

    /**
     * @param non-empty-string $call
     */
    public function add(string $call): void
    {
        $this->calls[] = $call;
    }
}
