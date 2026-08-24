<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Every ability the domain does not publish, forced closed by name. A missing
 * policy method is permissive — Filament falls through to `allow()` — which is
 * why the host's Admin panel cannot turn `strictAuthorization()` on: eight of
 * its resources, `ChatConversation` first among them, have no policy at all.
 *
 * `canCreate` and `canEdit` are in the list on purpose. A conversation is
 * opened by a customer through `OpenConversation`, and every change to one is a
 * transition the domain guards; a Filament edit form would assign a state
 * directly and skip the guard, the audit row and the timings all at once.
 *
 * The abilities that stay open — `canViewAny` and `canView` — are not here.
 * They are stated on the resource and answered by the domain's `CustodyPolicy`.
 */
trait DeniesUnpublishedResourceAbilities
{
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function canForceDelete(Model $record): bool
    {
        return false;
    }

    public static function canForceDeleteAny(): bool
    {
        return false;
    }

    public static function canReorder(): bool
    {
        return false;
    }

    public static function canReplicate(Model $record): bool
    {
        return false;
    }

    public static function canRestore(Model $record): bool
    {
        return false;
    }

    public static function canRestoreAny(): bool
    {
        return false;
    }
}
