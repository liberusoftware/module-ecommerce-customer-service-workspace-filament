<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Resources\Conversations;

use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Liberu\Ecommerce\CustomerServiceWorkspace\Data\ServiceMeasurement;
use Liberu\Ecommerce\CustomerServiceWorkspace\Enums\ConversationState;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Concerns\DeniesUnpublishedResourceAbilities;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Resources\Conversations\Pages\ConversationTimeline;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Resources\Conversations\Pages\ListConversations;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Resources\Conversations\Pages\ViewConversation;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Resources\Conversations\RelationManagers\HistoryRelationManager;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Resources\Conversations\RelationManagers\NotesRelationManager;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Resources\Conversations\RelationManagers\RequestsRelationManager;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Resources\Conversations\RelationManagers\TranscriptRelationManager;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Support\PanelTenant;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Support\Render;
use Liberu\Ecommerce\CustomerServiceWorkspace\Models\Conversation;
use Liberu\Ecommerce\CustomerServiceWorkspace\Policies\CustodyPolicy;
use Liberu\Ecommerce\CustomerServiceWorkspace\Queries\MeasureService;
use Liberu\Ecommerce\CustomerServiceWorkspace\Queries\QueuePosition;
use UnitEnum;

/**
 * One merchant's conversations, and the workspace an agent answers one in.
 *
 * `canViewAny` and `canView` are stated and answered by the domain's
 * `CustodyPolicy`; every other ability is forced closed by name. Filament
 * returns `allow()` for an ability no policy covers, which is why the host's
 * Admin panel cannot enable `strictAuthorization()` at all — `ChatConversation`
 * is the first of eight resources there with no policy.
 *
 * The route key is `reference` rather than the row id: the reference is the
 * module's to mint and carries none of the host's own numbering. Resolution
 * runs over the tenant-scoped query, so another merchant's reference and one
 * that does not exist are the same `ModelNotFoundException`.
 *
 * The customer's email is on the record screen and on no listing. A queue is
 * read by picking a row, and picking a row needs a name.
 */
final class ConversationResource extends Resource
{
    use DeniesUnpublishedResourceAbilities;

    protected static ?string $model = Conversation::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $recordTitleAttribute = 'reference';

    protected static ?string $modelLabel = 'conversation';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'Conversations';

    protected static UnitEnum|string|null $navigationGroup = 'Customer service';

    protected static ?int $navigationSort = 20;

    public static function getRecordRouteKeyName(): string
    {
        return 'reference';
    }

    /** A panel with no merchant has no conversations to be about. */
    public static function canViewAny(): bool
    {
        return PanelTenant::resolvable();
    }

    /**
     * Custody, asked of the domain. The tenant-scoped query already makes
     * another merchant's row unresolvable; this is the same answer stated where
     * Filament asks for it, so the ability is decided rather than defaulted.
     */
    public static function canView(Model $record): bool
    {
        return $record instanceof Conversation
            && PanelTenant::resolvable()
            && CustodyPolicy::agentMayWork($record, PanelTenant::current());
    }

    /** @return Builder<Conversation> */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Conversation> $query */
        $query = parent::getEloquentQuery();

        return $query->where('tenant_id', PanelTenant::current())->withCount('messages');
    }

    /** @return array<class-string> */
    public static function getRelations(): array
    {
        return [
            TranscriptRelationManager::class,
            NotesRelationManager::class,
            RequestsRelationManager::class,
            HistoryRelationManager::class,
        ];
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Where it stands')
                ->columns(4)
                ->schema([
                    TextEntry::make('state')
                        ->label('State')
                        ->badge()
                        ->state(fn (Conversation $record): string => Render::stateLabel($record->state))
                        ->color(fn (Conversation $record): string => Render::stateColour($record->state)),
                    TextEntry::make('queue_position')
                        ->label('Place in the queue')
                        ->state(fn (Conversation $record): string => Render::queuePosition(self::position($record)))
                        ->helperText('Derived from arrival order at the moment you asked, never stored. A conversation that has left the queue has no place in it, which is not the same as being first.'),
                    TextEntry::make('agent_ref')
                        ->label('Agent')
                        ->placeholder(Render::NONE)
                        ->helperText('Whoever took it. A handover replaces this and does not restart the clock.'),
                    TextEntry::make('channel')->label('Channel')->badge(),
                ]),

            Section::make('How the service went')
                ->description('Every figure is a subtraction over recorded timestamps and every one runs from arrival, so the queue wait is inside all three. A figure that has not happened yet is shown as unmeasured; none of them is ever a zero.')
                ->columns(4)
                ->schema([
                    TextEntry::make('wait')
                        ->label('Waited')
                        ->state(fn (Conversation $record): string => Render::duration(self::measure($record)->waitSeconds))
                        ->helperText('Arrival until an agent took it, or until it was given up on.'),
                    TextEntry::make('first_reply')
                        ->label('First agent reply')
                        ->state(fn (Conversation $record): string => Render::duration(self::measure($record)->firstReplySeconds))
                        ->helperText('Arrival until the agent actually said something — not until assignment, which is what the host measured under this name.'),
                    TextEntry::make('resolution')
                        ->label('Resolution')
                        ->state(fn (Conversation $record): string => Render::duration(self::measure($record)->resolutionSeconds))
                        ->helperText('Arrival until it closed, either way. Still open reads as unmeasured.'),
                    TextEntry::make('rating')
                        ->label('The customer said')
                        ->state(fn (Conversation $record): string => Render::rating(self::measure($record)->rating))
                        ->helperText('Given once, by the participant, against a resolved conversation. This desk cannot give one and cannot change one.'),
                ]),

            Section::make('The customer')
                ->columns(4)
                ->schema([
                    TextEntry::make('participant_name')->label('Name')->placeholder(Render::NONE),
                    TextEntry::make('participant_email')
                        ->label('Email')
                        ->placeholder(Render::NONE)
                        ->copyable()
                        ->helperText('On this screen and on no listing.'),
                    TextEntry::make('participant_ref')
                        ->label('Reference')
                        ->copyable()
                        ->helperText('Opaque here. Erasure replaces it, and the conversation and its timings stay.'),
                    TextEntry::make('reference')
                        ->label('Conversation')
                        ->copyable()
                        ->helperText('Names the conversation. It is not the claim that proves the customer may read it — that is minted once, hashed, and never shown to anyone, including this desk.'),
                ]),

            Section::make('When')
                ->columns(5)
                ->schema([
                    TextEntry::make('queued_at')->label('Arrived')->dateTime(),
                    TextEntry::make('assigned_at')->label('Taken')->dateTime()->placeholder(Render::NONE),
                    TextEntry::make('first_agent_reply_at')->label('First reply')->dateTime()->placeholder(Render::NONE),
                    TextEntry::make('resolved_at')->label('Resolved')->dateTime()->placeholder(Render::NONE),
                    TextEntry::make('abandoned_at')->label('Given up on')->dateTime()->placeholder(Render::NONE),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')->label('Conversation')->copyable()->searchable(),
                TextColumn::make('state')
                    ->label('State')
                    ->badge()
                    ->formatStateUsing(fn (ConversationState $state): string => Render::stateLabel($state))
                    ->color(fn (ConversationState $state): string => Render::stateColour($state))
                    ->sortable(),
                TextColumn::make('participant_name')
                    ->label('Customer')
                    ->placeholder(Render::NONE)
                    ->searchable()
                    ->tooltip('The name given when the conversation was opened. The email is on the record screen only.'),
                TextColumn::make('channel')->label('Channel')->badge()->sortable(),
                TextColumn::make('agent_ref')->label('Agent')->placeholder(Render::NONE)->searchable()->sortable(),
                TextColumn::make('messages_count')
                    ->label('Lines')
                    ->sortable()
                    ->tooltip('Counted through the conversation\'s own relation on read. There is no stored message counter, because the host\'s three disagreed with each other.'),
                TextColumn::make('queued_at')->label('Arrived')->dateTime()->sortable(),
                TextColumn::make('resolved_at')->label('Resolved')->dateTime()->placeholder(Render::NONE)->sortable(),
            ])
            ->filters([
                SelectFilter::make('state')->label('State')->options(fn (): array => self::stateOptions()),
                SelectFilter::make('agent_ref')->label('Agent')->options(fn (): array => self::agentOptions()),
            ])
            ->defaultSort('queued_at', 'desc')
            ->toolbarActions([]);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ListConversations::route('/'),
            'view' => ViewConversation::route('/{record}'),
            'timeline' => ConversationTimeline::route('/{record}/timeline'),
        ];
    }

    /** @return array<string, string> */
    public static function stateOptions(): array
    {
        $options = [];

        foreach (ConversationState::cases() as $state) {
            $options[$state->value] = Render::stateLabel($state);
        }

        return $options;
    }

    /** @return array<string, string> */
    public static function agentOptions(): array
    {
        /** @var array<string, string> $agents */
        $agents = self::getEloquentQuery()
            ->whereNotNull('agent_ref')
            ->distinct()
            ->orderBy('agent_ref')
            ->pluck('agent_ref', 'agent_ref')
            ->all();

        return $agents;
    }

    /**
     * The domain's figures for one conversation.
     *
     * ponytail: memoised per row, because four entries read it and each read is
     * a query for the rating. Dropped when a page mounts and after every write,
     * so no figure outlives the thing it measured.
     *
     * @var array<int, ServiceMeasurement>
     */
    private static array $measurements = [];

    public static function measure(Conversation $record): ServiceMeasurement
    {
        return self::$measurements[(int) $record->id] ??= (new MeasureService())((string) $record->tenant_id, $record);
    }

    public static function position(Conversation $record): ?int
    {
        return (new QueuePosition())((string) $record->tenant_id, $record);
    }

    public static function forgetMeasurements(): void
    {
        self::$measurements = [];
    }
}
