<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Resources\Conversations\Pages;

use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\App;
use Liberu\Ecommerce\CustomerServiceWorkspace\Actions\AbandonConversation;
use Liberu\Ecommerce\CustomerServiceWorkspace\Actions\AssignAgent;
use Liberu\Ecommerce\CustomerServiceWorkspace\Actions\MarkMessagesRead;
use Liberu\Ecommerce\CustomerServiceWorkspace\Actions\PostMessage;
use Liberu\Ecommerce\CustomerServiceWorkspace\Actions\RequestAction;
use Liberu\Ecommerce\CustomerServiceWorkspace\Actions\ResolveConversation;
use Liberu\Ecommerce\CustomerServiceWorkspace\Actions\WriteNote;
use Liberu\Ecommerce\CustomerServiceWorkspace\Data\Outcome;
use Liberu\Ecommerce\CustomerServiceWorkspace\Enums\Author;
use Liberu\Ecommerce\CustomerServiceWorkspace\Enums\ConversationState;
use Liberu\Ecommerce\CustomerServiceWorkspace\Enums\NoteVisibility;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Resources\Conversations\ConversationResource;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Support\Apply;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Support\PanelAgent;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Support\PanelTenant;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Support\Render;
use Liberu\Ecommerce\CustomerServiceWorkspace\Models\Conversation;
use Liberu\Ecommerce\CustomerServiceWorkspace\Models\Note;

/**
 * One conversation and everything a desk may do to it.
 *
 * Two rules hold across every action here, and the host broke both.
 *
 * A move the conversation cannot make is not offered — `ChatService::assignAgent`
 * assigned in any state, so a closed conversation reopened and its resolution
 * time was rewritten. And a hidden control is not a control: every action
 * re-reads the conversation through `FindConversation` and acts on what it finds,
 * so a conversation somebody else resolved while this page sat open is refused
 * by the domain and the refusal is rendered, rather than being decided by a
 * button's visibility against a stale copy.
 *
 * Nothing here writes through Eloquent. The host's panel dashboard re-queried
 * the service and re-implemented assignment inline, checking a state the service
 * did not check and skipping the timestamp and the system line the service wrote:
 * two assignment paths with different behaviour.
 */
final class ViewConversation extends ViewRecord
{
    protected static string $resource = ConversationResource::class;

    public function mount(int|string $record): void
    {
        ConversationResource::forgetMeasurements();

        parent::mount($record);
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('take')
                ->label('Take it')
                ->icon('heroicon-o-hand-raised')
                ->color('primary')
                ->modalHeading('Take this conversation')
                ->modalDescription('Records who is answering and when it was taken. A handover to another agent is the same action and does not restart the clock; taking one that has been resolved is refused rather than reopening it.')
                ->modalSubmitActionLabel('Take it')
                ->requiresConfirmation()
                ->visible(fn (Conversation $record): bool => PanelAgent::resolvable()
                    && $record->state->canTransitionTo(ConversationState::Assigned))
                ->action(fn (Conversation $record) => Apply::to(
                    $record,
                    'Taken',
                    'It is yours. The customer is waiting on a reply, which is what the first-reply figure measures.',
                    'It was already yours, and the clock was not restarted.',
                    fn (Conversation $fresh, string $tenant): Outcome => App::make(AssignAgent::class)($tenant, $fresh, PanelAgent::current()),
                )),

            Action::make('reply')
                ->label('Reply')
                ->icon('heroicon-o-paper-airplane')
                ->modalHeading('Reply to the customer')
                ->modalDescription('The first line an agent writes is what time-to-first-response is measured from. The host wrote that figure at assignment, before anybody had said anything.')
                ->modalSubmitActionLabel('Send')
                ->schema([
                    Textarea::make('body')
                        ->label('Your reply')
                        ->required()
                        ->rows(6)
                        ->maxLength(65535),
                ])
                ->visible(fn (Conversation $record): bool => PanelAgent::resolvable()
                    && $record->state === ConversationState::Assigned)
                ->action(function (Conversation $record, array $data) {
                    /** @var array{body: string} $data */
                    return Apply::to(
                        $record,
                        'Sent',
                        'The customer has your reply.',
                        'That line was already on the transcript.',
                        fn (Conversation $fresh, string $tenant): Outcome => App::make(PostMessage::class)(
                            $tenant,
                            $fresh,
                            Author::Agent,
                            PanelAgent::current(),
                            $data['body'],
                        ),
                    );
                }),

            Action::make('resolve')
                ->label('Resolve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Resolve this conversation')
                ->modalDescription('Closes it and stamps the resolution. The resolution time runs from arrival, not from assignment, so a customer who waited forty minutes and was answered in two is a forty-two minute resolution.')
                ->modalSubmitActionLabel('Resolve')
                ->visible(fn (Conversation $record): bool => $record->state->canTransitionTo(ConversationState::Resolved))
                ->action(fn (Conversation $record) => Apply::to(
                    $record,
                    'Resolved',
                    'Closed, and the customer may now rate it. Only the participant can, and only once.',
                    'It was already resolved, and the original resolution time stands.',
                    fn (Conversation $fresh, string $tenant): Outcome => App::make(ResolveConversation::class)($tenant, $fresh),
                )),

            Action::make('abandon')
                ->label('Give up on it')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Record that nobody reached this conversation')
                ->modalDescription('For a conversation that queued and was never answered. It is measured, because the queue wait is the measurement — the host recorded nothing at all for exactly these, so the customers who got no service were the ones missing from the numbers.')
                ->modalSubmitActionLabel('Give up on it')
                ->visible(fn (Conversation $record): bool => $record->state->canTransitionTo(ConversationState::Abandoned))
                ->action(fn (Conversation $record) => Apply::to(
                    $record,
                    'Recorded as abandoned',
                    'The wait is measured and counted against this merchant\'s service, which is the point of recording it.',
                    'It was already recorded as abandoned.',
                    fn (Conversation $fresh, string $tenant): Outcome => App::make(AbandonConversation::class)($tenant, $fresh),
                )),

            Action::make('markRead')
                ->label('Mark the customer\'s lines read')
                ->icon('heroicon-o-envelope-open')
                ->color('gray')
                ->visible(fn (): bool => PanelTenant::resolvable())
                ->action(function (Conversation $record): void {
                    $outcome = Apply::outcome(
                        $record,
                        fn (Conversation $fresh, string $tenant): Outcome => App::make(MarkMessagesRead::class)($tenant, $fresh, Author::Agent),
                    );

                    if ($outcome === null) {
                        return;
                    }

                    Notification::make()
                        ->title($outcome->count === 0 ? 'Nothing was unread' : 'Marked read')
                        ->body($outcome->count.' '.Render::plural($outcome->count, 'line').' the customer wrote '
                            .Render::plural($outcome->count, 'was', 'were').' unread until now.')
                        ->color($outcome->count === 0 ? 'gray' : 'success')
                        ->send();
                }),

            Action::make('note')
                ->label('Write a note')
                ->icon('heroicon-o-pencil-square')
                ->color('gray')
                ->modalHeading('Write a note about this conversation')
                ->modalDescription('A note is written once and never edited. Publishing one to the customer is a publication: the correction is another note. A system note has no author and is not an agent\'s to write, so it is not offered here.')
                ->modalSubmitActionLabel('Write it')
                ->schema([
                    Select::make('visibility')
                        ->label('Who may read it')
                        ->required()
                        ->default(NoteVisibility::Internal->value)
                        ->options(fn (): array => self::visibilityOptions()),
                    Textarea::make('body')
                        ->label('The note')
                        ->required()
                        ->rows(5)
                        ->maxLength(65535),
                ])
                ->visible(fn (): bool => PanelAgent::resolvable())
                ->action(function (Conversation $record, array $data) {
                    /** @var array{visibility: string, body: string} $data */
                    $visibility = NoteVisibility::from($data['visibility']);

                    return Apply::to(
                        $record,
                        'Written',
                        $visibility === NoteVisibility::CustomerVisible
                            ? 'The customer can read it. It cannot be edited away; a correction is another note.'
                            : 'On the record, and the customer cannot read it.',
                        'That note was already written.',
                        fn (Conversation $fresh, string $tenant): Outcome => App::make(WriteNote::class)(
                            $tenant,
                            Note::SUBJECT_CONVERSATION,
                            $fresh->reference,
                            $visibility,
                            PanelAgent::current(),
                            $data['body'],
                        ),
                    );
                }),

            Action::make('requestAction')
                ->label('Ask another module to act')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('warning')
                ->modalHeading('Ask the module that owns this to do something')
                ->modalDescription('This workspace never refunds, cancels or ships anything itself: it records that you asked and what the owning module answered. The request is written down before it is transmitted, so a request nobody answered is still a row somebody can find.')
                ->modalSubmitActionLabel('Ask')
                ->schema([
                    TextInput::make('kind')
                        ->label('What to ask for')
                        ->required()
                        ->maxLength(64)
                        ->helperText('The owning module\'s own name for the operation — `refund`, `cancel`, `resend_shipment`. Opaque here.'),
                    TextInput::make('target_ref')
                        ->label('What to do it to')
                        ->required()
                        ->maxLength(255)
                        ->helperText('The order, payment or shipment reference the owning module knows. This module never resolves it.'),
                ])
                ->visible(fn (): bool => PanelAgent::resolvable())
                ->action(function (Conversation $record, array $data): void {
                    /** @var array{kind: string, target_ref: string} $data */
                    $outcome = Apply::outcome(
                        $record,
                        // Minted here, per submission. The domain publishes no
                        // minter and a caller-held reference is what stops a
                        // retry authorising a second refund.
                        fn (Conversation $fresh, string $tenant): Outcome => App::make(RequestAction::class)(
                            $tenant,
                            $fresh,
                            bin2hex(random_bytes(16)),
                            $data['kind'],
                            $data['target_ref'],
                            PanelAgent::current(),
                        ),
                    );

                    if ($outcome === null) {
                        return;
                    }

                    Notification::make()
                        ->title($outcome->happened() ? 'Confirmed' : 'Recorded, not confirmed')
                        ->body(Render::actionRequest($outcome))
                        ->color(Render::outcomeColour($outcome))
                        ->persistent()
                        ->send();
                }),
        ];
    }

    /** @return array<string, string> The two an agent may choose. `System` is the domain's to write and is refused if it arrives. */
    public static function visibilityOptions(): array
    {
        return [
            NoteVisibility::Internal->value => Render::visibilityLabel(NoteVisibility::Internal),
            NoteVisibility::CustomerVisible->value => Render::visibilityLabel(NoteVisibility::CustomerVisible),
        ];
    }

}
