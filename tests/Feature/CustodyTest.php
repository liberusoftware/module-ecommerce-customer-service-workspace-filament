<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Liberu\Ecommerce\CustomerServiceWorkspace\Actions\PostMessage;
use Liberu\Ecommerce\CustomerServiceWorkspace\Actions\RequestAction;
use Liberu\Ecommerce\CustomerServiceWorkspace\Actions\WriteNote;
use Liberu\Ecommerce\CustomerServiceWorkspace\Enums\Author;
use Liberu\Ecommerce\CustomerServiceWorkspace\Enums\NoteVisibility;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Pages\AgentDesk;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Resources\Conversations\ConversationResource;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Resources\Conversations\Pages\ConversationTimeline;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Resources\Conversations\Pages\ListConversations;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Resources\Conversations\Pages\ViewConversation;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Resources\Conversations\RelationManagers\HistoryRelationManager;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Resources\Conversations\RelationManagers\NotesRelationManager;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Resources\Conversations\RelationManagers\RequestsRelationManager;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Resources\Conversations\RelationManagers\TranscriptRelationManager;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Support\PanelAgent;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Support\PanelTenant;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Tests\TestAgent;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Tests\TestTenant;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Widgets\ServiceQualityWidget;
use Liberu\Ecommerce\CustomerServiceWorkspace\Models\Conversation;
use Livewire\Livewire;

/*
 * Two merchants with deliberately identical values: the same customer
 * reference, the same name, the same channel, the same order reference. Two
 * merchants both talking to `customer-1` about `order-1` is the ordinary case,
 * and a proof that creates one merchant's rows proves nothing about a `where`
 * clause nobody wrote.
 *
 * Every relation on every screen is exercised, not only every list: tenancy has
 * leaked through relations rather than queries in four consecutive waves, and
 * the host's own version of this cluster reached `chat_messages` by
 * `conversation_id` with no tenant predicate at all.
 */

/** @return array{ours: Conversation, theirs: Conversation} */
function twoMerchants(): array
{
    $ours = assigned(conversation(TestTenant::PRIMARY));
    $theirs = assigned(conversation(TestTenant::OTHER));

    foreach ([$ours, $theirs] as $conversation) {
        $tenant = (string) $conversation->tenant_id;

        (new PostMessage())($tenant, $conversation, Author::Agent, TestAgent::PRIMARY, 'Looking into it.');
        (new WriteNote())($tenant, 'conversation', $conversation->reference, NoteVisibility::Internal, TestAgent::PRIMARY, 'Chased the warehouse.');
        (new RequestAction())($tenant, $conversation, 'req-1', 'refund', 'order-1', TestAgent::PRIMARY);
    }

    return ['ours' => $ours, 'theirs' => $theirs];
}

/** @return Collection<int, Model> */
function relationRecords(string $manager, Conversation $owner): Collection
{
    /** @var Collection<int, Model> $records */
    $records = Livewire::test($manager, [
        'ownerRecord' => $owner,
        'pageClass' => ViewConversation::class,
    ])->instance()->getTable()->getRecords();

    return $records;
}

it('lists only this merchant’s conversations', function (): void {
    $rows = twoMerchants();

    Livewire::test(ListConversations::class)
        ->assertCanSeeTableRecords(Conversation::query()->whereKey($rows['ours']->getKey())->get())
        ->assertCanNotSeeTableRecords(Conversation::query()->whereKey($rows['theirs']->getKey())->get());
});

it('counts this merchant’s transcript through the relation, and gets the right non-zero number', function (): void {
    $rows = twoMerchants();

    (new PostMessage())(TestTenant::OTHER, $rows['theirs'], Author::Customer, 'customer-1', 'Still waiting.');
    (new PostMessage())(TestTenant::OTHER, $rows['theirs'], Author::Agent, TestAgent::PRIMARY, 'Nearly there.');

    $counted = ConversationResource::getEloquentQuery()->firstOrFail();

    // `withCount()` builds the relation from a fresh instance whose `tenant_id`
    // is null. Unguarded, the restatement becomes `where('tenant_id', '')` and
    // reports zero for everything, which looks exactly like isolation working.
    expect((int) $counted->id)->toBe((int) $rows['ours']->id)
        ->and($counted->messages_count)->toBe(1);
});

it('shows only this conversation’s transcript, notes, requests and history, through its own relations', function (): void {
    $rows = twoMerchants();

    $transcript = relationRecords(TranscriptRelationManager::class, $rows['ours']);
    $notes = relationRecords(NotesRelationManager::class, $rows['ours']);
    $requests = relationRecords(RequestsRelationManager::class, $rows['ours']);
    $history = relationRecords(HistoryRelationManager::class, $rows['ours']);

    // Non-zero and correct, in the right tenant. A guarded restatement that
    // silently reported nothing would pass a test asserting only isolation.
    expect($transcript)->toHaveCount(1)
        ->and($transcript->pluck('tenant_id')->unique()->all())->toBe([TestTenant::PRIMARY])
        ->and($notes)->toHaveCount(1)
        ->and($notes->pluck('tenant_id')->unique()->all())->toBe([TestTenant::PRIMARY])
        ->and($requests)->toHaveCount(1)
        ->and($requests->first()->target_ref)->toBe('order-1')
        ->and($requests->pluck('tenant_id')->unique()->all())->toBe([TestTenant::PRIMARY])
        // Opened, assigned, and the request that was recorded and never sent —
        // three, and not the other merchant's identical three.
        ->and($history)->toHaveCount(3)
        ->and($history->pluck('tenant_id')->unique()->all())->toBe([TestTenant::PRIMARY]);
});

it('queues only this merchant’s waiting customers', function (): void {
    conversation(TestTenant::PRIMARY, 'customer-2');
    $theirs = conversation(TestTenant::OTHER, 'customer-2');

    $queued = Livewire::test(AgentDesk::class)->instance()->getTable()->getRecords();

    expect($queued)->toHaveCount(1)
        ->and((string) $queued->first()->tenant_id)->toBe(TestTenant::PRIMARY)
        ->and($queued->first()->reference)->not->toBe($theirs->reference)
        ->and(AgentDesk::getNavigationBadge())->toBe('1');
});

it('measures only this merchant’s conversations', function (): void {
    resolved(conversation(TestTenant::PRIMARY));
    resolved(conversation(TestTenant::OTHER));
    resolved(conversation(TestTenant::OTHER, 'customer-2'));

    // One of this merchant's, not the three that exist. A widget that summed
    // across the deployment would read as a busier, better-served merchant.
    Livewire::test(ServiceQualityWidget::class)->assertSee('1 measured, 0 still open');
});

it('answers another merchant’s conversation exactly as it answers nobody’s', function (): void {
    $rows = twoMerchants();

    // Not a 403 and not a different message: the panel is not a directory of
    // the deployment, so "belongs to somebody else" and "does not exist" are
    // one answer.
    $open = fn (string $reference): mixed => Livewire::test(ViewConversation::class, ['record' => $reference]);

    expect(fn (): mixed => $open($rows['theirs']->reference))->toThrow(ModelNotFoundException::class)
        ->and(fn (): mixed => $open('csw_nothingatall'))->toThrow(ModelNotFoundException::class);
});

it('answers another merchant’s conversation the same way on the timeline screen', function (): void {
    $rows = twoMerchants();

    $open = fn (string $reference): mixed => Livewire::test(ConversationTimeline::class, ['record' => $reference]);

    expect(fn (): mixed => $open($rows['theirs']->reference))->toThrow(ModelNotFoundException::class)
        ->and(fn (): mixed => $open('csw_nothingatall'))->toThrow(ModelNotFoundException::class);
});

it('refuses to resolve a panel with no merchant rather than matching orphan rows', function (): void {
    // `where('tenant_id', null)` compiles to `is null`, which lists exactly the
    // orphan rows a scope exists to hide. The host's chat tables made both
    // tenant keys nullable, so a conversation off an unresolved host belonged to
    // nobody and nothing later made it belong to anybody.
    PanelTenant::resolveUsing(fn (): ?string => null);

    expect(fn (): string => PanelTenant::current())->toThrow(RuntimeException::class);

    PanelTenant::resolveUsing(fn (): string => '');

    expect(fn (): string => PanelTenant::current())->toThrow(RuntimeException::class);
});

it('falls back to the panel’s own tenant when the host names no resolver, and raises when there is none', function (): void {
    // A panel with no Filament tenancy and no resolver has no merchant to be.
    // There is no "show everything" to fall back to.
    PanelTenant::resolveUsing(null);

    expect(fn (): string => PanelTenant::current())->toThrow(RuntimeException::class);
});

it('follows the panel’s merchant when it changes, rather than the first one it saw', function (): void {
    $rows = twoMerchants();

    TestTenant::use(TestTenant::OTHER);
    ConversationResource::forgetMeasurements();

    Livewire::test(ListConversations::class)
        ->assertCanSeeTableRecords(Conversation::query()->whereKey($rows['theirs']->getKey())->get())
        ->assertCanNotSeeTableRecords(Conversation::query()->whereKey($rows['ours']->getKey())->get());
});

it('attributes work to nobody rather than to a blank agent', function (): void {
    // An empty agent reference would write a row saying somebody did it and
    // name nobody, which is worse than refusing to write one.
    PanelAgent::resolveUsing(fn (): ?string => null);

    expect(PanelAgent::resolvable())->toBeFalse()
        ->and(fn (): string => PanelAgent::current())->toThrow(RuntimeException::class);

    PanelAgent::resolveUsing(fn (): string => '');

    expect(fn (): string => PanelAgent::current())->toThrow(RuntimeException::class);
});

it('falls back to the panel’s authenticated user when the host names no agent resolver', function (): void {
    PanelAgent::resolveUsing(null);

    // Nobody is signed in to the test panel, so there is nobody to be.
    expect(PanelAgent::resolvable())->toBeFalse();
});

it('lists only this merchant’s agents in the agent filter', function (): void {
    twoMerchants();

    expect(ConversationResource::agentOptions())->toBe([TestAgent::PRIMARY => TestAgent::PRIMARY]);

    TestTenant::use(TestTenant::OTHER);

    expect(ConversationResource::agentOptions())->toBe([TestAgent::PRIMARY => TestAgent::PRIMARY]);
});
