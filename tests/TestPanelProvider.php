<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Tests;

use Filament\Panel;
use Filament\PanelProvider;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\CustomerServiceWorkspacePlugin;

/** A merchant panel with this module's plugin attached and nothing else — the whole of what a host writes. */
final class TestPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->plugin(
                CustomerServiceWorkspacePlugin::make()
                    ->tenantUsing(fn (): string => TestTenant::current())
                    ->agentUsing(fn (): ?string => TestAgent::current()),
            );
    }
}
