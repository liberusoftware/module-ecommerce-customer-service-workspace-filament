<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerServiceWorkspace\Filament;

use Illuminate\Support\ServiceProvider;

/**
 * Registers a view namespace and nothing else. Every screen arrives through
 * {@see CustomerServiceWorkspacePlugin}, so the host decides which panels get
 * the desk — a provider that registered resources would put a customer's
 * transcript on whatever panel happened to boot.
 */
class CustomerServiceWorkspaceFilamentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'ecommerce-customer-service-workspace-filament');
    }
}
