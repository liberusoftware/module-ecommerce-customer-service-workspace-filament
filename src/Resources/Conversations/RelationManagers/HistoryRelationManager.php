<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Resources\Conversations\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Liberu\Ecommerce\CustomerServiceWorkspace\Enums\EventKind;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Concerns\DeniesUnpublishedRelationAbilities;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Support\Render;
use Liberu\Ecommerce\CustomerServiceWorkspace\Models\ConversationEvent;

/**
 * Every move this conversation has made, in sequence.
 *
 * Append-only in the domain, enforced in model hooks and arbitrated by a unique
 * `(conversation, sequence)` index, so nothing here edits, deletes or
 * dissociates a row. The host had a state column with no guard and no ledger at
 * all: a closed conversation could be reassigned, and nothing recorded that it
 * had been.
 */
final class HistoryRelationManager extends RelationManager
{
    use DeniesUnpublishedRelationAbilities;

    protected static string $relationship = 'events';

    protected static ?string $title = 'History';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sequence')->label('#'),
                TextColumn::make('kind')
                    ->label('What happened')
                    ->badge()
                    ->formatStateUsing(fn (EventKind $state): string => Render::eventLabel($state)),
                TextColumn::make('payload')
                    ->label('Detail')
                    ->state(fn (ConversationEvent $record): string => Render::payload($record->payload))
                    ->wrap(),
                TextColumn::make('occurred_at')->label('When')->dateTime(),
            ])
            ->filters([])
            ->paginated(false)
            ->defaultSort('sequence')
            ->recordUrl(null)
            ->recordAction(null)
            ->recordActions([])
            ->headerActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Nothing has happened to this conversation')
            ->emptyStateDescription('Not even the opening, which should be impossible: a conversation exists because one was recorded.');
    }
}
