<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\App;
use Liberu\Ecommerce\CustomerServiceWorkspace\Actions\AssignAgent;
use Liberu\Ecommerce\CustomerServiceWorkspace\Data\Outcome;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Resources\Conversations\ConversationResource;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Support\Apply;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Support\PanelAgent;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Support\PanelTenant;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Support\Render;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Widgets\ServiceQualityWidget;
use Liberu\Ecommerce\CustomerServiceWorkspace\Models\Conversation;
use Liberu\Ecommerce\CustomerServiceWorkspace\Queries\ListQueue;
use Liberu\Ecommerce\CustomerServiceWorkspace\Queries\QueuePosition;
use UnitEnum;

/**
 * The customers waiting, and how this merchant's service is going.
 *
 * The host had a page with this name and it was a second implementation of its
 * own service: it re-queried what `ChatService::getAgentConversations()` already
 * returned, and it assigned an agent inline, checking a state the service did
 * not check and skipping the timestamp and the system line the service wrote.
 * Two assignment paths with different behaviour, and the panel's was the one
 * nobody tested.
 *
 * So nothing on this page decides anything. The rows are `Queries\ListQueue`,
 * each place in the queue is `Queries\QueuePosition`, the figures are
 * `Queries\MeasureService`, and taking a conversation is `Actions\AssignAgent` —
 * the same call the record screen makes.
 */
final class AgentDesk extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?string $navigationLabel = 'Waiting';

    protected static UnitEnum|string|null $navigationGroup = 'Customer service';

    protected static ?int $navigationSort = 10;

    protected static ?string $title = 'Customers waiting for an agent';

    protected static ?string $slug = 'customer-service/waiting';

    protected string $view = 'ecommerce-customer-service-workspace-filament::pages.agent-desk';

    /** A panel with no merchant has no queue to be about. */
    public static function canAccess(): bool
    {
        return PanelTenant::resolvable();
    }

    public static function getNavigationBadge(): ?string
    {
        if (! PanelTenant::resolvable()) {
            return null;
        }

        $waiting = (new ListQueue())(PanelTenant::current())->count();

        return $waiting === 0 ? null : (string) $waiting;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    public function getSubheading(): string
    {
        return 'In arrival order within this merchant. A place in this queue is derived at the moment you look, never stored: '
            .'the host stored one, read the global maximum without a lock to assign it, and never cleared it on the way out.';
    }

    /** @return array<class-string> */
    protected function getHeaderWidgets(): array
    {
        return [
            ServiceQualityWidget::class,
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): Collection => $this->waiting())
            ->columns([
                TextColumn::make('position')
                    ->label('Place')
                    ->state(fn (Conversation $record): string => Render::queuePosition($this->position($record)))
                    ->tooltip('Counted from arrival order at the moment this page loaded. A conversation that has left the queue has no place in it.'),
                TextColumn::make('participant_name')->label('Customer')->placeholder(Render::NONE),
                TextColumn::make('channel')->label('Channel')->badge(),
                TextColumn::make('queued_at')->label('Waiting since')->dateTime(),
                TextColumn::make('reference')->label('Conversation')->copyable(),
            ])
            ->paginated(false)
            // A queue read in arrival order. A filter on it is a way of not
            // looking at somebody who is waiting.
            ->filters([])
            ->recordUrl(fn (Conversation $record): string => ConversationResource::getUrl('view', ['record' => $record->reference]))
            ->recordAction(null)
            ->recordActions([])
            ->headerActions([])
            ->toolbarActions([])
            ->emptyStateIcon('heroicon-o-check-circle')
            ->emptyStateHeading('Nobody is waiting')
            ->emptyStateDescription('Every conversation this merchant has is with an agent, resolved, or recorded as one nobody reached.');
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('takeNext')
                ->label('Take the one who has waited longest')
                ->icon('heroicon-o-hand-raised')
                ->requiresConfirmation()
                ->modalHeading('Take the conversation at the front of the queue')
                ->modalDescription('Assigns you to whoever arrived first and still has nobody. It is the same call the record screen makes: there is one assignment path here, because the host had two and they behaved differently.')
                ->modalSubmitActionLabel('Take it')
                ->visible(fn (): bool => PanelAgent::resolvable())
                ->action(fn (): null => $this->takeNext()),
        ];
    }

    private function takeNext(): null
    {
        $next = $this->waiting()->first();

        if ($next === null) {
            Notification::make()
                ->title('Nobody is waiting')
                ->body('The queue is empty, so nothing was taken.')
                ->color('gray')
                ->send();

            return null;
        }

        Apply::to(
            $next,
            'Taken',
            ($next->participant_name ?? 'The customer').' is yours. They arrived '.$next->queued_at->diffForHumans().'.',
            'It was already yours.',
            fn (Conversation $fresh, string $tenant): Outcome => App::make(AssignAgent::class)($tenant, $fresh, PanelAgent::current()),
        );

        return null;
    }

    /**
     * The queue, from the domain.
     *
     * ponytail: memoised for the request, because the table, the take action and
     * every row's place read it. One `QueuePosition` runs per row, which is a
     * count each — bounded by the length of a queue somebody is expected to work
     * through by hand, and if that stops being true the domain is where a
     * batched position belongs.
     *
     * @var Collection<int, Conversation>|null
     */
    private ?Collection $waiting = null;

    /** @return Collection<int, Conversation> */
    private function waiting(): Collection
    {
        return $this->waiting ??= (new ListQueue())(PanelTenant::current());
    }

    private function position(Conversation $record): ?int
    {
        return (new QueuePosition())(PanelTenant::current(), $record);
    }
}
