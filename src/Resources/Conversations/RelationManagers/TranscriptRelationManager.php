<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Resources\Conversations\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Liberu\Ecommerce\CustomerServiceWorkspace\Enums\Author;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Concerns\DeniesUnpublishedRelationAbilities;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Support\Render;
use Liberu\Ecommerce\CustomerServiceWorkspace\Models\Message;

/**
 * What was said, in sequence, through the conversation's own relation — which
 * restates the tenant on top of the foreign key, because the relation is where
 * tenancy has leaked for four consecutive waves.
 *
 * A redacted line keeps its row, its author and its timestamp and loses its
 * body, so an erasure takes the words and leaves every count and duration
 * derived from them.
 */
final class TranscriptRelationManager extends RelationManager
{
    use DeniesUnpublishedRelationAbilities;

    protected static string $relationship = 'messages';

    protected static ?string $title = 'Transcript';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sequence')->label('#'),
                TextColumn::make('author')
                    ->label('Who')
                    ->badge()
                    ->formatStateUsing(fn (Author $state): string => Render::authorLabel($state))
                    ->color(fn (Author $state): string => Render::authorColour($state)),
                TextColumn::make('body')
                    ->label('Said')
                    ->wrap()
                    ->description(fn (Message $record): ?string => $record->isRedacted() ? 'Redacted. The row, its author and its timing are still here; the words are gone.' : null),
                TextColumn::make('sent_at')->label('When')->dateTime(),
                TextColumn::make('read_at')
                    ->label('Read')
                    ->dateTime()
                    ->placeholder(Render::NONE)
                    ->tooltip('Blank means nobody on the other side has marked it read, which is not the same as unsent.'),
            ])
            ->filters([])
            ->paginated(false)
            ->defaultSort('sequence')
            ->recordUrl(null)
            ->recordAction(null)
            ->recordActions([])
            ->headerActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Nothing has been said yet')
            ->emptyStateDescription('The conversation was opened and no line has been written in it, by anybody.');
    }
}
