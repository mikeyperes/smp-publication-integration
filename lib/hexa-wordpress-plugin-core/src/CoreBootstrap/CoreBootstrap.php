<?php

namespace Hexa\PluginCore\CoreBootstrap;

use Hexa\PluginCore\CoreContracts\ModuleInterface;
use Hexa\PluginCore\CoreContracts\PluginContextInterface;
use Hexa\PluginCore\IntegrationTests\IntegrationTestRuntime;
use Hexa\PluginCore\QuerySafety\StaticFrontPageQueryGuard;

final class CoreBootstrap {
    private PluginContextInterface $context;

    /**
     * @var ModuleInterface[]
     */
    private array $modules = [];

    private bool $booted = false;

    public function __construct( PluginContextInterface $context ) {
        $this->context = $context;
    }

    public function context(): PluginContextInterface {
        return $this->context;
    }

    public function add_module( ModuleInterface $module ): self {
        $this->modules[] = $module;

        return $this;
    }

    public function boot(): void {
        if ( $this->booted ) {
            return;
        }

        ( new StaticFrontPageQueryGuard() )->register();
        IntegrationTestRuntime::register_host( $this->context );

        foreach ( $this->modules as $module ) {
            $module->register();
        }

        $this->booted = true;
    }
}
