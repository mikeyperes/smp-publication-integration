<?php

namespace smp_publication_integration\Bootstrap;

use SMP\PublicationIntegration\Runtime\Plugin as RuntimePlugin;

/** Legacy class name retained for integrations that instantiate it directly. */
final class Plugin {
    private RuntimePlugin $runtime;

    public function __construct() {
        $this->runtime = new RuntimePlugin();
    }

    public function boot(): void {
        $this->runtime->boot();
    }
}
