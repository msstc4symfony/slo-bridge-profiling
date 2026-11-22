<?php

declare(strict_types=1);

namespace Msstc4Symfony\SloBridgeProfiling\Test\Integration;

use Msstc4Symfony\ProfilingBundle\Application\ProfilingFactoryInterface;
use Msstc4Symfony\SloBridgeProfiling\Infrastructure\Profiling\ProfiledOperationMonitor;
use Msstc4Symfony\SloBridgeProfiling\SloBridgeProfilingBundle;
use Msstc4Symfony\SloBridgeProfiling\Test\Fixtures\RecordingLogger;
use Msstc4Symfony\SloBridgeProfiling\Test\Fixtures\RecordingOperationMonitor;
use Msstc4Symfony\SloBridgeProfiling\Test\Fixtures\RecordingProfilingFactory;
use Msstc4Symfony\SloBridgeProfiling\Test\Fixtures\StubClassifier;
use Msstc4Symfony\SloBridgeProfiling\Test\Fixtures\StubSloCatalog;
use Msstc4Symfony\SloBundle\Application\Port\ExpectedFailureClassifierInterface;
use Msstc4Symfony\SloBundle\Application\Port\OperationMonitorInterface;
use Msstc4Symfony\SloBundle\Domain\Repository\SloCatalogInterface;
use Override;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
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

        $definition = $container->getDefinition(ProfiledOperationMonitor::class);
        self::assertSame([OperationMonitorInterface::class, null, 0], $definition->getDecoratedService());
        self::assertSame([['channel' => 'slo_bundle']], $definition->getTag('monolog.logger'));

        $container->compile(true);

        self::assertInstanceOf(ProfiledOperationMonitor::class, $container->get(OperationMonitorInterface::class));
    }

    public function testInjectsTheAutowiredLogger(): void
    {
        $container = $this->container(failingFactory: true);
        $container->register('logger', RecordingLogger::class)->setPublic(true);
        $container->setAlias(LoggerInterface::class, 'logger');
        $container->compile(true);

        $monitor = $container->get(OperationMonitorInterface::class);
        self::assertInstanceOf(ProfiledOperationMonitor::class, $monitor);
        self::assertSame(42, $monitor->measure(StubSloCatalog::DECLARED, static fn (): int => 42));

        $logger = $container->get('logger');
        self::assertInstanceOf(RecordingLogger::class, $logger);
        self::assertSame(['SLO operation span could not be opened, the operation is not profiled.'], array_column($logger->records, 'message'));
    }

    public function testTheMonitorWorksWithoutALoggerService(): void
    {
        $container = $this->container();
        $container->compile(true);

        $monitor = $container->get(OperationMonitorInterface::class);
        self::assertInstanceOf(ProfiledOperationMonitor::class, $monitor);
        self::assertSame(42, $monitor->measure(StubSloCatalog::DECLARED, static fn (): int => 42));
    }

    public function testKernelResetStaysOnTheInnerMonitor(): void
    {
        $container = $this->container();
        $capture = new class implements CompilerPassInterface {
            /**
             * @var list<string>
             */
            public array $resettable = [];

            #[Override]
            public function process(ContainerBuilder $container): void
            {
                $this->resettable = array_keys($container->findTaggedServiceIds('kernel.reset'));
            }
        };
        // Captured where Symfony's ResettableServicePass reads the tag.
        $container->addCompilerPass($capture, PassConfig::TYPE_BEFORE_REMOVING);
        $container->compile(true);

        self::assertSame(['inner_monitor'], $capture->resettable);
    }

    private function container(bool $failingFactory = false): ContainerBuilder
    {
        $container = new ContainerBuilder();
        // Symfony 7.4 reads both in ExtensionTrait::executeConfiguratorCallback().
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.build_dir', sys_get_temp_dir());
        // slo-bundle exposes the interface as an alias of a kernel.reset-tagged OperationMonitor.
        $container->register('inner_monitor', RecordingOperationMonitor::class)->addTag('kernel.reset', ['method' => 'reset']);
        $container->setAlias(OperationMonitorInterface::class, 'inner_monitor')->setPublic(true);
        $container->register(ProfilingFactoryInterface::class, RecordingProfilingFactory::class)->setArgument('$failOnCreate', $failingFactory);
        $container->register(SloCatalogInterface::class, StubSloCatalog::class)->setFactory([StubSloCatalog::class, 'declaring']);
        $container->register(ExpectedFailureClassifierInterface::class, StubClassifier::class);

        $bundle = new SloBridgeProfilingBundle();
        $bundle->getContainerExtension()?->load([], $container);
        $bundle->build($container);

        return $container;
    }
}
