<?php

declare(strict_types=1);

namespace Msstc4Symfony\SloBridgeProfiling\Test\Fixtures;

use Msstc4Symfony\MetricsBundle\Application\Metric\MetricDefinitionInterface;
use Msstc4Symfony\MetricsBundle\Application\Port\MetricRecorderInterface;
use Override;

/**
 * @phpstan-type RecordedCall array{method: 'increment'|'set'|'observe', metric: MetricDefinitionInterface, value: float|int|null, labels: list<scalar>}
 */
final class RecordingMetricRecorder implements MetricRecorderInterface
{
    /** @var list<RecordedCall> */
    public array $calls = [];

    #[Override]
    public function increment(MetricDefinitionInterface $metric, array $labels = []): void
    {
        $this->calls[] = ['method' => 'increment', 'metric' => $metric, 'value' => null, 'labels' => $labels];
    }

    #[Override]
    public function set(MetricDefinitionInterface $metric, float|int $value, array $labels = []): void
    {
        $this->calls[] = ['method' => 'set', 'metric' => $metric, 'value' => $value, 'labels' => $labels];
    }

    #[Override]
    public function observe(MetricDefinitionInterface $metric, float $value, array $labels = []): void
    {
        $this->calls[] = ['method' => 'observe', 'metric' => $metric, 'value' => $value, 'labels' => $labels];
    }
}
