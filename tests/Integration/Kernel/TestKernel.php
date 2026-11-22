<?php

declare(strict_types=1);

namespace Msstc4Symfony\SloBridgeProfiling\Test\Integration\Kernel;

use Msstc4Symfony\LogicBundle\LogicBundle;
use Msstc4Symfony\MetricsBundle\Application\Port\MetricRecorderInterface;
use Msstc4Symfony\MetricsBundle\MetricsBundle;
use Msstc4Symfony\ProfilingBundle\ProfilingBundle;
use Msstc4Symfony\SloBridgeProfiling\SloBridgeProfilingBundle;
use Msstc4Symfony\SloBridgeProfiling\Test\Fixtures\RecordingEndProcessor;
use Msstc4Symfony\SloBridgeProfiling\Test\Fixtures\RecordingMetricRecorder;
use Msstc4Symfony\SloBridgeProfiling\Test\Fixtures\ThrowingMetricRecorder;
use Msstc4Symfony\SloBundle\SloBundle;
use Override;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\MonologBundle\MonologBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @param list<string>|null $spanBlacklist msstc4symfony_profiling.spans.blacklist
     */
    public function __construct(
        string $environment,
        bool $debug,
        private readonly ?array $spanBlacklist = null,
        private readonly bool $unreachableStorage = false,
    ) {
        parent::__construct($environment, $debug);
    }

    // Per process: parallel PHPUnit runs must not wipe each other's container.
    public static function cacheRoot(): string
    {
        return sys_get_temp_dir() . '/msstc4symfony-slo-bridge-profiling-test-' . getmypid();
    }

    #[Override]
    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new MonologBundle();
        yield new MetricsBundle();
        yield new SloBundle();
        yield new ProfilingBundle();
        if (class_exists(LogicBundle::class)) {
            yield new LogicBundle();
        }

        yield new SloBridgeProfilingBundle();
    }

    // One container per configuration: a cached one would ignore the constructor flags.
    #[Override]
    public function getCacheDir(): string
    {
        return self::cacheRoot() . '/cache/' . $this->environment . '-' . md5(serialize([$this->spanBlacklist, $this->unreachableStorage]));
    }

    #[Override]
    public function getLogDir(): string
    {
        return self::cacheRoot() . '/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'http_method_override' => false,
            'test' => true,
            // The php_errors logger installs a global handler that outlives the kernel and trips failOnRisky.
            'php_errors' => ['log' => false],
        ]);
        $container->extension('monolog', ['handlers' => ['main' => ['type' => 'test']]]);
        $container->extension('msstc4symfony_metrics', [
            'storage' => ['dsn' => 'inmemory://'],
            'application_name' => 'shop',
            'component_name' => 'api',
        ]);
        $container->extension('msstc4symfony_slo', [
            'operations' => [
                'payment.validation' => ['objectives' => [
                    ['type' => 'availability', 'target' => 99.9],
                    ['type' => 'latency', 'threshold' => '50ms', 'target' => 99],
                ]],
                'payment.capture' => ['objectives' => [['type' => 'availability', 'target' => 99.9]]],
            ],
        ]);
        $container->extension('msstc4symfony_profiling', ['spans' => ['blacklist' => $this->spanBlacklist]]);

        $recorder = $this->unreachableStorage ? ThrowingMetricRecorder::class : RecordingMetricRecorder::class;
        $services = $container->services();
        $services->set(RecordingEndProcessor::class)->autoconfigure()->public();
        $services->set($recorder)->public();
        $services->alias(MetricRecorderInterface::class, $recorder);
    }
}
