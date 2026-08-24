<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Every relation-manager ability forced closed by name. `canAssociate()` and
 * `canDissociate()` are live on a `hasMany` and default open: dissociating a
 * message from its conversation would move a line of somebody's transcript to
 * another merchant's, with no edit form and no audit row.
 *
 * The domain's messages, notes and events are append-only in model hooks, so a
 * delete here would raise rather than return — an ability that throws instead
 * of refusing is still an ability that was offered.
 *
 * `canViewAny()` is stated rather than inherited, so a table's visibility does
 * not rest on a policy method nobody wrote. Declared public where the parent
 * declares them protected: widening is legal, narrowing is fatal, and public is
 * what lets a test ask by name.
 */
trait DeniesUnpublishedRelationAbilities
{
    public function canViewAny(): bool
    {
        return true;
    }

    public function canAssociate(): bool
    {
        return false;
    }

    public function canAttach(): bool
    {
        return false;
    }

    public function canCreate(): bool
    {
        return false;
    }

    public function canDelete(Model $record): bool
    {
        return false;
    }

    public function canDeleteAny(): bool
    {
        return false;
    }

    public function canDetach(Model $record): bool
    {
        return false;
    }

    public function canDetachAny(): bool
    {
        return false;
    }

    public function canDissociate(Model $record): bool
    {
        return false;
    }

    public function canDissociateAny(): bool
    {
        return false;
    }

    public function canEdit(Model $record): bool
    {
        return false;
    }

    public function canForceDelete(Model $record): bool
    {
        return false;
    }

    public function canForceDeleteAny(): bool
    {
        return false;
    }

    public function canReorder(): bool
    {
        return false;
    }

    public function canReplicate(Model $record): bool
    {
        return false;
    }

    public function canRestore(Model $record): bool
    {
        return false;
    }

    public function canRestoreAny(): bool
    {
        return false;
    }

    public function canView(Model $record): bool
    {
        return false;
    }
}
