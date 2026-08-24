<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Liberu\Ecommerce\CustomerServiceWorkspace\Actions\ForgetParticipant;
use Liberu\Ecommerce\CustomerServiceWorkspace\Data\Timeline;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Resources\Conversations\Pages\ConversationTimeline;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Support\Render;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Tests\Fakes\FakeTimelineSource;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Tests\TestTenant;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
 * The timeline screen.
 *
 * Assembled at the moment of reading and stored nowhere, which means an empty
 * one is ambiguous: nothing happened, or nobody was asked. This screen never
 * leaves that ambiguous — every source that could not be asked is named, and
 * named with the reason, because an operator who has not connected payments
 * needs a different next step from one whose payments module is down.
 */

function timeline(string $reference): Testable
{
    return Livewire::test(ConversationTimeline::class, ['record' => $reference]);
}

it('names every unbound source rather than showing an empty list', function (): void {
    // Every seam is unbound as the domain ships it. An empty timeline here is
    // four questions nobody answered, not a customer who has done nothing.
    $conversation = conversation();

    timeline($conversation->reference)
        ->assertSee('orders: not connected')
        ->assertSee('payments: not connected')
        ->assertSee('shipments: not connected')
        ->assertSee('returns: not connected')
        ->assertSee('not a picture of nothing');
});

it('tells a source that could not be asked apart from one nobody bound', function (): void {
    $conversation = conversation();
    $broken = new FakeTimelineSource('payments');
    $broken->throw = true;

    Config::set('customer-service-workspace.seams.timeline', ['orders' => null, 'payments' => $broken]);

    timeline($conversation->reference)
        ->assertSee('orders: not connected')
        ->assertSee('payments: could not be asked');
});

it('asks every bound source about the participant, and shows what it holds', function (): void {
    // The subject is the customer, not the conversation: another module can key
    // on a customer, and this module holds no mapping from a conversation to an
    // order. An agent who wants one names it on the request form.
    $conversation = conversation(TestTenant::PRIMARY, 'customer-7');
    $orders = new FakeTimelineSource('orders');

    Config::set('customer-service-workspace.seams.timeline', ['orders' => $orders]);

    timeline($conversation->reference)
        ->assertSee('order-for-customer-7')
        ->assertSee('placed')
        // A nested payload value is not passed off as a scalar.
        ->assertSee('total: 19.99')
        ->assertSee('Every source this workspace knows about answered');

    expect($orders->asked)->toBe([[
        'tenantId' => TestTenant::PRIMARY,
        'subjectKind' => 'participant',
        'subjectRef' => 'customer-7',
    ]]);
});

it('says a complete timeline with nothing in it is complete and empty', function (): void {
    $conversation = conversation();

    Config::set('customer-service-workspace.seams.timeline', []);

    timeline($conversation->reference)
        ->assertSee('Nothing has happened to this customer anywhere')
        ->assertSee('none of them holds anything');
});

it('asks nobody about a participant who has been erased', function (): void {
    // After erasure the participant reference is the redaction token, and every
    // erased conversation carries the same one. Asking a payments module about
    // `redacted` would be asking about the wrong person, loudly, across the
    // whole merchant.
    $conversation = resolved();
    $orders = new FakeTimelineSource('orders');

    Config::set('customer-service-workspace.seams.timeline', ['orders' => $orders]);

    (new ForgetParticipant())(TestTenant::PRIMARY, 'customer-1');

    timeline($conversation->reference)
        ->assertSee('There is nobody to ask about')
        ->assertSee('asking about the wrong person');

    expect($orders->asked)->toBe([]);
});

it('says an empty timeline with an unbound source is not an absence of events', function (): void {
    $empty = new Timeline([], [], ['orders']);

    expect(Render::answeredSources($empty))->toBe(Render::NONE)
        ->and(Render::timelineCoverage($empty))->toContain('Not everything is here')
        ->and(Render::timelineCoverage($empty))->toContain('orders')
        ->and(Render::timelineCoverage($empty))->toContain('not an absence of events');
});

it('names a skipped source with the reason it was skipped', function (): void {
    $bound = new FakeTimelineSource('orders');

    Config::set('customer-service-workspace.seams.timeline', ['orders' => $bound, 'payments' => null]);

    $timeline = new Timeline([], ['notes'], ['payments', 'orders']);

    expect(Render::skippedSources($timeline))->toBe('payments: not connected; orders: could not be asked')
        ->and(Render::timelineColour($timeline))->toBe('warning');
});
