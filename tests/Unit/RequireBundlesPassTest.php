<?php

declare(strict_types=1);

namespace Msstc4Symfony\SloBridgeProfiling\Test\Unit;

use LogicException;
use Msstc4Symfony\ProfilingBundle\Application\ProfilingFactoryInterface;
use Msstc4Symfony\SloBridgeProfiling\DependencyInjection\CompilerPass\RequireBundlesPass;
use Msstc4Symfony\SloBridgeProfiling\Test\Fixtures\RecordingOperationMonitor;
use Msstc4Symfony\SloBridgeProfiling\Test\Fixtures\RecordingProfilingFactory;
use Msstc4Symfony\SloBundle\Application\Port\OperationMonitorInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversClass(RequireBundlesPass::class)]
final class RequireBundlesPassTest extends TestCase
{
    #[DoesNotPerformAssertions]
    public function testPassesWhenBothBundlesAreRegistered(): void
    {
        $container = new ContainerBuilder();
        $container->register(OperationMonitorInterface::class, RecordingOperationMonitor::class);
        $container->register('profiling_factory', RecordingProfilingFactory::class);
        $container->setAlias(ProfilingFactoryInterface::class, 'profiling_factory');

        (new RequireBundlesPass())->process($container);
    }

    public function testFailsWithoutSloBundle(): void
    {
        $container = new ContainerBuilder();
        $container->register(ProfilingFactoryInterface::class, RecordingProfilingFactory::class);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('SloBridgeProfilingBundle needs SloBundle: register Msstc4Symfony\SloBundle\SloBundle in config/bundles.php.');

        (new RequireBundlesPass())->process($container);
    }

    public function testFailsWithoutProfilingBundle(): void
    {
        $container = new ContainerBuilder();
        $container->register(OperationMonitorInterface::class, RecordingOperationMonitor::class);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('SloBridgeProfilingBundle needs ProfilingBundle: register Msstc4Symfony\ProfilingBundle\ProfilingBundle in config/bundles.php.');

        (new RequireBundlesPass())->process($container);
    }
}
