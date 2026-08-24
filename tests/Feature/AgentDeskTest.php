<?php

declare(strict_types=1);

use Filament\Actions\Testing\TestAction;
use Liberu\Ecommerce\CustomerServiceWorkspace\Actions\AbandonConversation;
use Liberu\Ecommerce\CustomerServiceWorkspace\Actions\RecordRating;
use Liberu\Ecommerce\CustomerServiceWorkspace\Enums\ConversationState;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Pages\AgentDesk;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Support\PanelAgent;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Tests\Claims;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Tests\TestAgent;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Tests\TestTenant;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Widgets\ServiceQualityWidget;
use Liberu\Ecommerce\CustomerServiceWorkspace\Models\Conversation;
use Livewire\Livewire;

/*
 * The queue screen. The host had a page with this name and it was a second
 * implementation of its own service: it re-queried what the service already
 * returned and assigned an agent inline, checking a state the service did not
 * check and skipping the timestamp and the system line the service wrote.
 *
 * Nothing on this page decides anything. Every row, every place and every figure
 * is a published domain query, and taking a conversation is the same action the
 * record screen calls.
 */

it('lists the queue in arrival order, with the place the domain derives', function (): void {
    $first = conversation(TestTenant::PRIMARY, 'customer-1');
    $second = conversation(TestTenant::PRIMARY, 'customer-2');

    Livewire::test(AgentDesk::class)
        ->assertSee('#1')
        ->assertSee('#2')
        ->assertSee($first->reference)
        ->assertSee($second->reference);
});

it('takes the customer who has waited longest, through the same action the record screen calls', function (): void {
    $first = conversation(TestTenant::PRIMARY, 'customer-1');
    conversation(TestTenant::PRIMARY, 'customer-2');

    Livewire::test(AgentDesk::class)->callAction(TestAction::make('takeNext'));

    $said = lastNotification();
    $first->refresh();

    expect($first->state)->toBe(ConversationState::Assigned)
        ->and($first->agent_ref)->toBe(TestAgent::PRIMARY)
        // Written by `AssignAgent`, which is the only thing that assigns here.
        ->and($first->events()->where('kind', 'assigned')->count())->toBe(1)
        ->and($said['title'])->toBe('Taken')
        ->and($said['color'])->toBe('success');
});

it('says nobody is waiting rather than acting on an empty queue', function (): void {
    Livewire::test(AgentDesk::class)->callAction(TestAction::make('takeNext'));

    $said = lastNotification();

    expect($said['title'])->toBe('Nobody is waiting')
        ->and($said['color'])->toBe('gray')
        ->and(Conversation::query()->count())->toBe(0);
});

it('shows nothing to take when there is nobody to take it', function (): void {
    conversation();

    PanelAgent::resolveUsing(fn (): ?string => null);

    Livewire::test(AgentDesk::class)->assertActionHidden('takeNext');
});

it('badges the queue with a number, and shows no badge when it is empty', function (): void {
    expect(AgentDesk::getNavigationBadge())->toBeNull()
        ->and(AgentDesk::getNavigationBadgeColor())->toBe('warning');

    conversation();

    expect(AgentDesk::getNavigationBadge())->toBe('1');

    (new AbandonConversation())(TestTenant::PRIMARY, Conversation::query()->firstOrFail());

    expect(AgentDesk::getNavigationBadge())->toBeNull();
});

it('says the queue is empty rather than showing a blank table', function (): void {
    Livewire::test(AgentDesk::class)
        ->assertSee('Nobody is waiting')
        ->assertSee('recorded as one nobody reached');
});

it('reports every mean the domain could not take as unmeasured, never as zero', function (): void {
    // Nothing has closed, so there is nothing to take a mean over. A zero here
    // would read as instant service — the host's own resolution figure excluded
    // the queue wait entirely and recorded nothing at all for the abandoned.
    conversation();

    Livewire::test(ServiceQualityWidget::class)
        ->assertSee('Nothing has been waited through to an end yet.')
        ->assertSee('No agent has replied to a closed conversation yet.')
        ->assertSee('Nobody has rated a conversation.')
        ->assertSee('0 measured, 1 still open');
});

it('counts a conversation nobody reached, because those are the ones a service metric needs', function (): void {
    $abandoned = conversation();
    (new AbandonConversation())(TestTenant::PRIMARY, Conversation::query()->whereKey($abandoned->id)->firstOrFail());

    Livewire::test(ServiceQualityWidget::class)
        ->assertSee('Queued and never answered')
        // Abandoned in the queue is measured: the wait is the measurement.
        ->assertSee('1 measured, 0 still open');
});

it('reports a mean the domain could take', function (): void {
    resolved();

    Livewire::test(ServiceQualityWidget::class)
        ->assertSee('Arrival until an agent took it')
        ->assertSee('Every conversation that closed had somebody on it.')
        ->assertDontSee('Nothing has been waited through to an end yet.');
});

it('reports a rating the customer gave', function (): void {
    $conversation = resolved();

    (new RecordRating())(
        TestTenant::PRIMARY,
        $conversation,
        Claims::of($conversation->reference),
        4,
    );

    Livewire::test(ServiceQualityWidget::class)->assertSee('Over 1 rated conversation.');
});
