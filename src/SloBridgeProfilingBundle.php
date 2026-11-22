<?php

declare(strict_types=1);

namespace Msstc4Symfony\SloBridgeProfiling;

use Msstc4Symfony\SloBridgeProfiling\DependencyInjection\CompilerPass\RequireBundlesPass;
use Override;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class SloBridgeProfilingBundle extends AbstractBundle
{
    protected string $extensionAlias = 'msstc4symfony_slo_bridge_profiling';

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new RequireBundlesPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, RequireBundlesPass::PRIORITY);
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import(__DIR__ . '/Resources/config/services.php');
    }
}
