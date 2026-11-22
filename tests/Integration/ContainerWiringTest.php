<?php

declare(strict_types=1);

namespace Msstc4Symfony\SloBridgeProfiling\Test\Integration;

use Msstc4Symfony\ProfilingBundle\Application\ProfilingFactoryInterface;
use Msstc4Symfony\SloBridgeProfiling\Infrastructure\Profiling\ProfiledOperationMonitor;
use Msstc4Symfony\SloBridgeProfiling\SloBridgeProfilingBundle;
use Msstc4Symfony\SloBridgeProfiling\Test\Fixtures\RecordingOperationMonitor;
use Msstc4Symfony\SloBridgeProfiling\Test\Fixtures\RecordingProfilingFactory;
use Msstc4Symfony\SloBridgeProfiling\Test\Fixtures\StubClassifier;
use Msstc4Symfony\SloBridgeProfiling\Test\Fixtures\StubSloCatalog;
use Msstc4Symfony\SloBundle\Application\Port\ExpectedFailureClassifierInterface;
use Msstc4Symfony\SloBundle\Application\Port\OperationMonitorInterface;
use Msstc4Symfony\SloBundle\Domain\Repository\SloCatalogInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The extension and the decoration against stand-ins for the services of slo-bundle and profiling-bundle.
 */
#[CoversNothing]
final class ContainerWiringTest extends TestCase
{
    public function testDecoratesTheOperationMonitorWithTheProfilingMonitor(): void
    {
        $container = $this->container();
        $container->register('logger', NullLogger::class);

        $definition = $container->getDefinition(ProfiledOperationMonitor::class);
        self::assertSame([OperationMonitorInterface::class, null, 0], $definition->getDecoratedService());
        self::assertSame([['channel' => 'slo_bundle']], $definition->getTag('monolog.logger'));

        $container->compile(true);

        self::assertInstanceOf(ProfiledOperationMonitor::class, $container->get(OperationMonitorInterface::class));
    }

    public function testTheMonitorWorksWithoutALoggerService(): void
    {
        $container = $this->container();
        $container->compile(true);

        $monitor = $container->get(OperationMonitorInterface::class);
        self::assertInstanceOf(ProfiledOperationMonitor::class, $monitor);
        self::assertSame(42, $monitor->measure(StubSloCatalog::DECLARED, static fn (): int => 42));
    }

    private function container(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        // Symfony 7.4 reads both in ExtensionTrait::executeConfiguratorCallback().
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.build_dir', sys_get_temp_dir());
        $container->register('inner_monitor', RecordingOperationMonitor::class);
        $container->setAlias(OperationMonitorInterface::class, 'inner_monitor')->setPublic(true);
        $container->register(ProfilingFactoryInterface::class, RecordingProfilingFactory::class);
        $container->register(SloCatalogInterface::class, StubSloCatalog::class)->setFactory([StubSloCatalog::class, 'declaring']);
        $container->register(ExpectedFailureClassifierInterface::class, StubClassifier::class);

        $bundle = new SloBridgeProfilingBundle();
        $bundle->getContainerExtension()?->load([], $container);
        $bundle->build($container);

        return $container;
    }
}
