<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerServiceWorkspace\Filament;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Pages\AgentDesk;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Resources\Conversations\ConversationResource;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Support\PanelAgent;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Support\PanelTenant;

/**
 * The support desk, as the host attaches it.
 *
 *     $panel->plugin(
 *         CustomerServiceWorkspacePlugin::make()
 *             ->tenantUsing(fn (): string => (string) Filament::getTenant()?->getKey())
 *             ->agentUsing(fn (): string => (string) Filament::auth()->id()),
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

    /** Who the desk acts as. Without it, the panel's authenticated user is used. */
    public function agentUsing(?Closure $resolver): self
    {
        PanelAgent::resolveUsing($resolver);

        return $this;
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            ConversationResource::class,
        ]);

        $panel->pages([
            AgentDesk::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
