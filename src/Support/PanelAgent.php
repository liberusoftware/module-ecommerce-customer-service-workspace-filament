<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Support;

use Closure;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Who the desk is acting as. Every domain action that writes takes an agent
 * reference, so it is resolved in one place rather than at four call sites.
 *
 * It is an identity, never an entitlement: the host's `ChatController` decided
 * agency from `hasRole(['super_admin','admin'])`, which made an admin of one
 * merchant an agent on every other. Standing is `CustodyPolicy`'s answer over
 * the conversation's own tenant, and this class has no opinion about it.
 */
final class PanelAgent
{
    /** ponytail: one process-global resolver, matching PanelTenant. Move both onto the plugin if a host needs one rule per panel. */
    private static ?Closure $resolver = null;

    public static function resolveUsing(?Closure $resolver): void
    {
        self::$resolver = $resolver;
    }

    public static function current(): string
    {
        $agent = self::$resolver !== null ? (self::$resolver)() : Auth::id();

        if (! is_string($agent) && ! is_int($agent)) {
            throw new RuntimeException(
                'The support desk could not resolve an agent for this panel. '
                .'Set one with CustomerServiceWorkspacePlugin::make()->agentUsing(...) in the panel provider.'
            );
        }

        $agent = (string) $agent;

        if ($agent === '') {
            throw new RuntimeException('The support desk resolved an empty agent reference, which would attribute work to nobody.');
        }

        return $agent;
    }

    /** Whether an agent can be named at all. A desk with nobody at it is read-only, not broken. */
    public static function resolvable(): bool
    {
        try {
            self::current();
        } catch (RuntimeException) {
            return false;
        }

        return true;
    }
}
