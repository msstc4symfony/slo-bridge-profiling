<?php

declare(strict_types=1);

namespace Msstc4Symfony\SloBridgeProfiling\Test\Unit;

use Msstc4Symfony\SloBridgeProfiling\DependencyInjection\CompilerPass\RequireBundlesPass;
use Msstc4Symfony\SloBridgeProfiling\SloBridgeProfilingBundle;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversClass(SloBridgeProfilingBundle::class)]
final class SloBridgeProfilingBundleTest extends TestCase
{
    public function testExtensionAliasFollowsTheFamilyNaming(): void
    {
        self::assertSame('msstc4symfony_slo_bridge_profiling', (new SloBridgeProfilingBundle())->getContainerExtension()?->getAlias());
    }

    public function testBuildRegistersTheRequirementPass(): void
    {
        $container = new ContainerBuilder();
        $before = $this->noopPass();
        $after = $this->noopPass();
        $container->addCompilerPass($before, PassConfig::TYPE_BEFORE_OPTIMIZATION, 11);
        $container->addCompilerPass($after, PassConfig::TYPE_BEFORE_OPTIMIZATION, 9);

        (new SloBridgeProfilingBundle())->build($container);

        $passes = array_values(array_filter(
            $container->getCompilerPassConfig()->getBeforeOptimizationPasses(),
            static fn (CompilerPassInterface $pass): bool => $pass === $before || $pass === $after || $pass instanceof RequireBundlesPass,
        ));
        self::assertCount(3, $passes);
        self::assertSame($before, $passes[0]);
        self::assertInstanceOf(RequireBundlesPass::class, $passes[1]);
        self::assertSame($after, $passes[2]);
    }

    private function noopPass(): CompilerPassInterface
    {
        return new class implements CompilerPassInterface {
            #[Override]
            public function process(ContainerBuilder $container): void
            {
            }
        };
    }
}
