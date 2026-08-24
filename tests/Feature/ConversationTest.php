<?php

declare(strict_types=1);

use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Config;
use Liberu\Ecommerce\CustomerServiceWorkspace\Actions\AbandonConversation;
use Liberu\Ecommerce\CustomerServiceWorkspace\Actions\AssignAgent;
use Liberu\Ecommerce\CustomerServiceWorkspace\Actions\ForgetParticipant;
use Liberu\Ecommerce\CustomerServiceWorkspace\Actions\PostMessage;
use Liberu\Ecommerce\CustomerServiceWorkspace\Actions\ResolveConversation;
use Liberu\Ecommerce\CustomerServiceWorkspace\Enums\Author;
use Liberu\Ecommerce\CustomerServiceWorkspace\Enums\ConversationState;
use Liberu\Ecommerce\CustomerServiceWorkspace\Enums\NoteVisibility;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Resources\Conversations\ConversationResource;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Resources\Conversations\Pages\ListConversations;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Resources\Conversations\Pages\ViewConversation;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Resources\Conversations\RelationManagers\TranscriptRelationManager;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Support\PanelAgent;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Support\Render;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Tests\Fakes\FakeActionGateway;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Tests\TestAgent;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Tests\TestTenant;
use Liberu\Ecommerce\CustomerServiceWorkspace\Models\ActionRequest;
use Liberu\Ecommerce\CustomerServiceWorkspace\Models\Conversation;
use Liberu\Ecommerce\CustomerServiceWorkspace\Models\Message;
use Liberu\Ecommerce\CustomerServiceWorkspace\Models\Note;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
 * The record screen: what it offers, what it refuses, and what it does when the
 * two disagree.
 *
 * A hidden button is not authorization. Every action here re-reads the
 * conversation through `FindConversation` and lets the domain decide, so a
 * conversation somebody else moved while this page sat open is refused rather
 * than acted on against a stale copy — which is the shape of the host's fault:
 * its panel dashboard checked `status === 'queued'` itself and its service
 * checked nothing at all.
 */

function desk(Conversation $conversation): Testable
{
    return Livewire::test(ViewConversation::class, ['record' => $conversation->reference]);
}

it('offers only the moves the conversation can make from where it is', function (): void {
    // Queued: it can be taken or given up on. It cannot be replied to, because
    // an agent replying to a queued conversation would be a second, silent
    // assignment path — the host had exactly that.
    desk(conversation())
        ->assertActionVisible('take')
        ->assertActionVisible('abandon')
        ->assertActionHidden('reply')
        // Nor resolved: a conversation nobody has taken cannot be closed as
        // though somebody had. It is given up on instead, and measured.
        ->assertActionHidden('resolve');

    desk(assigned())
        ->assertActionVisible('reply')
        ->assertActionVisible('resolve')
        // A conversation an agent has seen never becomes nobody's again.
        ->assertActionHidden('abandon');

    // Resolved is terminal. Nothing reopens it: the host's `assignAgent`
    // assigned in any state and overwrote `started_at`, so a resolved
    // conversation reopened and its resolution time was rewritten.
    desk(resolved())
        ->assertActionHidden('take')
        ->assertActionHidden('reply')
        ->assertActionHidden('resolve')
        ->assertActionHidden('abandon');
});

it('takes a conversation by calling the domain, and records what the domain records', function (): void {
    $conversation = conversation();

    desk($conversation)->callAction(TestAction::make('take'));

    $said = lastNotification();
    $conversation->refresh();

    expect($conversation->state)->toBe(ConversationState::Assigned)
        ->and($conversation->agent_ref)->toBe(TestAgent::PRIMARY)
        ->and($conversation->assigned_at)->not->toBeNull()
        // Written by `AssignAgent` and by nothing on this page: the host's panel
        // assigned inline and skipped both the timestamp and the audit row.
        ->and($conversation->events()->where('kind', 'assigned')->count())->toBe(1)
        ->and($said['title'])->toBe('Taken')
        ->and($said['color'])->toBe('success');
});

it('says nothing changed when the conversation is already this agent’s', function (): void {
    $conversation = assigned();

    desk($conversation)->callAction(TestAction::make('take'));

    $said = lastNotification();

    expect($said['title'])->toBe('Nothing changed')
        ->and($said['color'])->toBe('gray')
        ->and($conversation->refresh()->events()->where('kind', 'assigned')->count())->toBe(1);
});

it('offers no move a conversation somebody else moved can no longer make', function (): void {
    // The panel re-reads the record on every request, so a conversation another
    // agent took between two page loads stops offering the move it can no
    // longer make. That is the first half of the guard; `ApplyTest` is the
    // second, because a control the panel hid is not a control.
    $conversation = conversation();

    desk($conversation)->assertActionVisible('abandon');

    (new AssignAgent())(TestTenant::PRIMARY, Conversation::query()->findOrFail($conversation->id), TestAgent::OTHER);

    desk($conversation->refresh())->assertActionHidden('abandon');

    expect($conversation->state)->toBe(ConversationState::Assigned)
        ->and($conversation->abandoned_at)->toBeNull();
});

it('replies through the domain, and the first agent line is what the first-reply figure measures', function (): void {
    $conversation = assigned();

    desk($conversation)->callAction(TestAction::make('reply'), ['body' => 'On it now.']);

    $conversation->refresh();

    expect($conversation->messages()->where('author', 'agent')->count())->toBe(1)
        ->and($conversation->first_agent_reply_at)->not->toBeNull()
        ->and(Message::query()->where('body', 'On it now.')->firstOrFail()->author)->toBe(Author::Agent)
        ->and(lastNotification()['title'])->toBe('Sent');
});

it('stops offering a reply once the conversation is closed', function (): void {
    $conversation = assigned();

    desk($conversation)->assertActionVisible('reply');

    (new ResolveConversation())(TestTenant::PRIMARY, Conversation::query()->findOrFail($conversation->id));

    desk($conversation->refresh())->assertActionHidden('reply');

    expect(Message::query()->where('body', 'Too late.')->exists())->toBeFalse();
});

it('resolves through the domain and measures from arrival, not from assignment', function (): void {
    $conversation = assigned();

    desk($conversation)->callAction(TestAction::make('resolve'));

    $said = lastNotification();
    $conversation->refresh();

    expect($conversation->state)->toBe(ConversationState::Resolved)
        ->and($conversation->resolved_at)->not->toBeNull()
        ->and($said['title'])->toBe('Resolved')
        ->and($said['color'])->toBe('success');
});

it('records a conversation nobody reached, because the queue wait is the measurement', function (): void {
    $conversation = conversation();

    desk($conversation)->callAction(TestAction::make('abandon'));

    $conversation->refresh();

    expect($conversation->state)->toBe(ConversationState::Abandoned)
        ->and($conversation->abandoned_at)->not->toBeNull()
        ->and(lastNotification()['title'])->toBe('Recorded as abandoned');
});

it('marks the customer’s unread lines read, and says so when there were none', function (): void {
    $conversation = assigned();

    (new PostMessage())(TestTenant::PRIMARY, $conversation, Author::Customer, 'customer-1', 'Any news?');

    desk($conversation)->callAction(TestAction::make('markRead'));

    $said = lastNotification();

    expect($said['title'])->toBe('Marked read')
        ->and($said['body'])->toBe('1 line the customer wrote was unread until now.')
        ->and($conversation->messages()->where('author', 'customer')->whereNull('read_at')->count())->toBe(0);

    desk($conversation)->callAction(TestAction::make('markRead'));

    $again = lastNotification();

    expect($again['title'])->toBe('Nothing was unread')
        ->and($again['color'])->toBe('gray');
});

it('writes a note that cannot be edited away, and says so when it is published', function (): void {
    $conversation = assigned();

    desk($conversation)->callAction(TestAction::make('note'), [
        'visibility' => NoteVisibility::CustomerVisible->value,
        'body' => 'Your refund is on its way.',
    ]);

    $note = Note::query()->where('subject_ref', $conversation->reference)->firstOrFail();

    $said = lastNotification();

    expect($note->visibility)->toBe(NoteVisibility::CustomerVisible)
        ->and($note->author_ref)->toBe(TestAgent::PRIMARY)
        ->and($said['title'])->toBe('Written')
        ->and($said['body'])->toContain('cannot be edited away');
});

it('offers an agent only the two visibilities that are an agent’s to choose', function (): void {
    // A system note has no author and is not an agent's to write. It is not on
    // the form, and the domain refuses it if it arrives anyway.
    expect(array_keys(ViewConversation::visibilityOptions()))
        ->toBe([NoteVisibility::Internal->value, NoteVisibility::CustomerVisible->value]);
});

it('records a request to another module and never says nothing was recorded when a row exists', function (): void {
    // Nothing is bound, as the domain ships it. The request is persisted before
    // it is transmitted, so the row exists and an operator told otherwise would
    // ask again — and a retry of a refund is what the request reference exists
    // to stop.
    $conversation = assigned();

    desk($conversation)->callAction(TestAction::make('requestAction'), [
        'kind' => 'refund',
        'target_ref' => 'order-9',
    ]);

    $request = ActionRequest::query()->where('target_ref', 'order-9')->firstOrFail();

    $said = lastNotification();

    expect($request->state->value)->toBe('requested')
        ->and($request->agent_ref)->toBe(TestAgent::PRIMARY)
        ->and($said['title'])->toBe('Recorded, not confirmed')
        ->and($said['body'])->toContain('is recorded against this conversation')
        ->and($said['body'])->not->toContain('Nothing was recorded')
        ->and($said['color'])->toBe('danger');
});

it('records the owning module’s confirmation when something is bound to carry it', function (): void {
    $conversation = assigned();
    $gateway = new FakeActionGateway();
    Config::set('customer-service-workspace.seams.actions', $gateway);

    desk($conversation)->callAction(TestAction::make('requestAction'), [
        'kind' => 'refund',
        'target_ref' => 'order-9',
    ]);

    $said = lastNotification();

    expect($gateway->submissions)->toBe([['kind' => 'refund', 'targetRef' => 'order-9']])
        ->and(ActionRequest::query()->where('target_ref', 'order-9')->firstOrFail()->state->value)->toBe('confirmed')
        ->and($said['title'])->toBe('Confirmed')
        ->and($said['color'])->toBe('success');
});

it('offers nothing an agent could sign for when there is no agent', function (): void {
    // A desk nobody is signed in to is a desk that can read. Writing a row that
    // says somebody did it and names nobody is worse than not writing one.
    PanelAgent::resolveUsing(fn (): ?string => null);

    desk(assigned())
        ->assertActionHidden('take')
        ->assertActionHidden('reply')
        ->assertActionHidden('note')
        ->assertActionHidden('requestAction')
        // These two are the conversation's own moves, not an agent's signature.
        ->assertActionVisible('resolve');
});

it('shows every figure on the record screen, and none of the absent ones as zero', function (): void {
    $conversation = conversation();

    // Queued: it has a place, and the place is a place rather than a count.
    desk($conversation)
        ->assertSee('#1')
        ->assertSee('Not rated')
        ->assertSee(Render::NONE);

    // Nothing has been waited through to an end, so the domain measures none of
    // the three — and the screen renders every one of them as unmeasured. A zero
    // would read as instant service for a customer nobody has answered.
    $measured = ConversationResource::measure($conversation);

    expect($measured->waitSeconds)->toBeNull()
        ->and($measured->firstReplySeconds)->toBeNull()
        ->and($measured->resolutionSeconds)->toBeNull()
        ->and($measured->rating)->toBeNull()
        ->and(Render::duration($measured->resolutionSeconds))->toBe(Render::NONE);

    ConversationResource::forgetMeasurements();

    // Assigned: out of the queue, which is not first in it.
    desk(assigned($conversation))->assertSee('Not queued');
});

it('shows a customer’s email on the record screen and on no listing', function (): void {
    // Wave 11 shipped reviewer PII on a public listing. An agent picking a row
    // out of a queue needs a name; nothing about picking a row needs an email.
    $conversation = conversation();

    desk($conversation)->assertSee('casey@example.test');

    Livewire::test(ListConversations::class)
        ->assertSee('Casey')
        ->assertDontSee('casey@example.test');
});

it('never shows the participant’s claim, which is the customer’s proof and not the desk’s', function (): void {
    $conversation = conversation();

    desk($conversation)->assertDontSee($conversation->getRawOriginal('claim_hash'));
});

it('leaves a redacted transcript readable as redacted rather than as silence', function (): void {
    $conversation = resolved();

    (new ForgetParticipant())(TestTenant::PRIMARY, 'customer-1');

    $records = Livewire::test(
        TranscriptRelationManager::class,
        ['ownerRecord' => $conversation->refresh(), 'pageClass' => ViewConversation::class],
    );

    // The row, its author and its timing survive an erasure; the words do not.
    $records->assertSee('Redacted.');
});

it('records that nobody was reached even after the queue emptied around it', function (): void {
    $conversation = conversation();

    (new AbandonConversation())(TestTenant::PRIMARY, Conversation::query()->findOrFail($conversation->id));

    desk($conversation->refresh())
        ->assertActionHidden('abandon')
        ->assertActionHidden('take');
});
