<?php

declare(strict_types=1);

namespace Msstc4Symfony\SloBridgeProfiling\DependencyInjection\CompilerPass;

use LogicException;
use Msstc4Symfony\ProfilingBundle\Application\ProfilingFactoryInterface;
use Msstc4Symfony\ProfilingBundle\ProfilingBundle;
use Msstc4Symfony\SloBundle\Application\Port\OperationMonitorInterface;
use Msstc4Symfony\SloBundle\SloBundle;
use Override;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Turns a missing bundle into a readable message instead of a decoration error.
 *
 * @internal
 */
final class RequireBundlesPass implements CompilerPassInterface
{
    public const PRIORITY = 10;

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(OperationMonitorInterface::class)) {
            throw new LogicException(\sprintf('SloBridgeProfilingBundle needs SloBundle: register %s in config/bundles.php.', SloBundle::class));
        }

        if (!$container->has(ProfilingFactoryInterface::class)) {
            throw new LogicException(\sprintf('SloBridgeProfilingBundle needs ProfilingBundle: register %s in config/bundles.php.', ProfilingBundle::class));
        }
    }
}
