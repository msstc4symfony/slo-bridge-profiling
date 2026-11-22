<?php

declare(strict_types=1);

namespace Msstc4Symfony\SloBridgeProfiling\Test\Fixtures;

use Msstc4Symfony\MetricsBundle\Application\Metric\MetricDefinitionInterface;
use Msstc4Symfony\MetricsBundle\Application\Port\MetricRecorderInterface;
use Override;
use RuntimeException;

/**
 * Metric storage that cannot be reached.
 */
final class ThrowingMetricRecorder implements MetricRecorderInterface
{
    #[Override]
    public function increment(MetricDefinitionInterface $metric, array $labels = []): void
    {
        throw new RuntimeException('Metric storage is unreachable.');
    }

    #[Override]
    public function set(MetricDefinitionInterface $metric, float|int $value, array $labels = []): void
    {
        throw new RuntimeException('Metric storage is unreachable.');
    }

    #[Override]
    public function observe(MetricDefinitionInterface $metric, float $value, array $labels = []): void
    {
        throw new RuntimeException('Metric storage is unreachable.');
    }
}
