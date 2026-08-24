<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerServiceWorkspace\Filament;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Support\PanelTenant;

/**
 * The support desk, as the host attaches it.
 *
 *     $panel->plugin(
 *         CustomerServiceWorkspacePlugin::make()
 *             ->tenantUsing(fn (): string => (string) Filament::getTenant()?->getKey()),
 *     );
 */
final class CustomerServiceWorkspacePlugin implements Plugin
{
    public static function make(): self
    {
        return new self();
    }

    public function getId(): string
    {
        return 'ecommerce-customer-service-workspace';
    }

    /** How this panel names the merchant. Without it, the panel's own Filament tenant is used. */
    public function tenantUsing(?Closure $resolver): self
    {
        PanelTenant::resolveUsing($resolver);

        return $this;
    }

    public function register(Panel $panel): void
    {
        //
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
