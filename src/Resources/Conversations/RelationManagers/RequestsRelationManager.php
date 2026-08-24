<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Resources\Conversations\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Liberu\Ecommerce\CustomerServiceWorkspace\Enums\RequestState;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Concerns\DeniesUnpublishedRelationAbilities;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Support\Render;

/**
 * What an agent asked another module to do, and what it answered.
 *
 * This is where a request that was recorded and never transmitted stays visible
 * after the notification has gone. A row sitting at "asked, no answer yet" is
 * somebody's job, not progress.
 */
final class RequestsRelationManager extends RelationManager
{
    use DeniesUnpublishedRelationAbilities;

    protected static string $relationship = 'actionRequests';

    protected static ?string $title = 'Asked of other modules';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kind')->label('Asked for')->badge(),
                TextColumn::make('target_ref')->label('About')->copyable(),
                TextColumn::make('state')
                    ->label('Answer')
                    ->badge()
                    ->formatStateUsing(fn (RequestState $state): string => Render::requestStateLabel($state))
                    ->color(fn (RequestState $state): string => Render::requestStateColour($state)),
                TextColumn::make('message')->label('What it said')->placeholder(Render::NONE)->wrap(),
                TextColumn::make('remote_ref')
                    ->label('Their reference')
                    ->placeholder(Render::NONE)
                    ->copyable()
                    ->tooltip('Recorded, never keyed on. An identifier the other party mints is not this module\'s idempotency key.'),
                TextColumn::make('agent_ref')->label('Asked by')->placeholder(Render::NONE),
                TextColumn::make('requested_at')->label('Asked')->dateTime(),
                TextColumn::make('settled_at')
                    ->label('Answered')
                    ->dateTime()
                    ->placeholder(Render::NONE)
                    ->tooltip('Blank means nobody has answered. The row exists because the request is written down before it is sent.'),
            ])
            ->filters([])
            ->paginated(false)
            ->defaultSort('requested_at')
            ->recordUrl(null)
            ->recordAction(null)
            ->recordActions([])
            ->headerActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Nothing has been asked of anybody')
            ->emptyStateDescription('No agent has asked another module to refund, cancel or resend anything about this conversation.');
    }
}
