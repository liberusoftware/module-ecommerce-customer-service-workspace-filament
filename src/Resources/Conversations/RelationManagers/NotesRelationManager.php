<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Resources\Conversations\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Liberu\Ecommerce\CustomerServiceWorkspace\Enums\NoteVisibility;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Concerns\DeniesUnpublishedRelationAbilities;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Support\Render;
use Liberu\Ecommerce\CustomerServiceWorkspace\Models\Note;

/**
 * The notes written about this conversation.
 *
 * Nothing here edits or deletes one. A note is written once: a customer-visible
 * note is a publication, and the correction for a wrong one is another note.
 * The write is a header action on the record screen, calling `WriteNote`.
 */
final class NotesRelationManager extends RelationManager
{
    use DeniesUnpublishedRelationAbilities;

    protected static string $relationship = 'notes';

    protected static ?string $title = 'Notes';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('visibility')
                    ->label('Who may read it')
                    ->badge()
                    ->formatStateUsing(fn (NoteVisibility $state): string => Render::visibilityLabel($state))
                    ->color(fn (NoteVisibility $state): string => Render::visibilityColour($state)),
                TextColumn::make('body')
                    ->label('The note')
                    ->wrap()
                    ->description(fn (Note $record): ?string => $record->redacted_at === null ? null : 'Redacted.'),
                TextColumn::make('author_ref')
                    ->label('Written by')
                    ->placeholder(Render::NONE)
                    ->tooltip('A system note has no author, which is what stops an agent writing one.'),
                TextColumn::make('written_at')->label('When')->dateTime(),
            ])
            ->filters([
                SelectFilter::make('visibility')->label('Who may read it')->options(fn (): array => self::visibilityOptions()),
            ])
            ->paginated(false)
            ->defaultSort('written_at')
            ->recordUrl(null)
            ->recordAction(null)
            ->recordActions([])
            ->headerActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Nothing has been noted')
            ->emptyStateDescription('No agent has written anything about this conversation, and the workspace has had nothing to record about it.');
    }

    /** @return array<string, string> */
    private static function visibilityOptions(): array
    {
        $options = [];

        foreach (NoteVisibility::cases() as $visibility) {
            $options[$visibility->value] = Render::visibilityLabel($visibility);
        }

        return $options;
    }
}
