<?php

declare(strict_types=1);

use Msstc4Symfony\SloBridgeProfiling\Infrastructure\Profiling\ProfiledOperationMonitor;
use Msstc4Symfony\SloBundle\Application\Port\OperationMonitorInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();
    $services->defaults()->autowire()->autoconfigure();

    // $logger is autowired, not service('logger')->ignoreOnInvalid(): that passes null to the typed
    // parameter when no logger exists, while autowiring falls back to the NullLogger default.
    // MonologBundle binds the slo_bundle channel logger through the tag.
    $services->set(ProfiledOperationMonitor::class)
        ->decorate(OperationMonitorInterface::class)
        ->arg('$inner', service('.inner'))
        ->tag('monolog.logger', ['channel' => 'slo_bundle'])
    ;
};
