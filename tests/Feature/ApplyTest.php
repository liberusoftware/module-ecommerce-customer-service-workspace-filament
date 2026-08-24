<?php

declare(strict_types=1);

use Liberu\Ecommerce\CustomerServiceWorkspace\Data\Outcome;
use Liberu\Ecommerce\CustomerServiceWorkspace\Enums\RefusalReason;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Support\Apply;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Support\Render;
use Liberu\Ecommerce\CustomerServiceWorkspace\Models\Conversation;

/*
 * The second half of the transition guard.
 *
 * A control the panel hid is not a control. Filament re-reads the record from
 * the database on every request, so a conversation another agent moved between
 * two page loads stops offering the move — that is `ConversationTest`. This is
 * what happens when the two are asked in the same instant anyway: two agents in
 * two windows, a queued job and a person, a retried request. The domain refuses,
 * and the refusal is rendered rather than the request being decided by a button.
 *
 * These drive `Apply` directly, because the race cannot be produced from inside
 * one Livewire request and a guard nothing exercises is a guard nobody knows
 * works.
 */

it('renders a refusal as a refusal, and writes nothing', function (): void {
    $conversation = assigned();

    Apply::to(
        $conversation,
        'Taken',
        'It is yours.',
        'It was already yours.',
        fn (): Outcome => Outcome::refused(RefusalReason::IllegalTransition),
    );

    $said = lastNotification();

    expect($said['title'])->toBe('Refused')
        ->and($said['body'])->toBe('Nothing was recorded, because '.Render::refusal(RefusalReason::IllegalTransition).'.')
        ->and($said['color'])->toBe('danger');
});

it('hands the domain the conversation as it is now, not as the page saw it', function (): void {
    // The re-read is the point: the action is given whatever `FindConversation`
    // returns, so the guard runs against the row rather than against a copy.
    $conversation = assigned();
    $seen = null;

    Apply::to($conversation, 'done', 'did', 'already', function (Conversation $fresh, string $tenant) use (&$seen): Outcome {
        $seen = [$fresh->id, $tenant];

        return Outcome::recorded($fresh->id);
    });

    expect($seen)->toBe([$conversation->id, (string) $conversation->tenant_id])
        ->and(lastNotification()['title'])->toBe('done');
});

it('says nothing changed for a move that had already been made', function (): void {
    Apply::to(assigned(), 'Taken', 'It is yours.', 'It was already yours.', fn (): Outcome => Outcome::alreadyRecorded(1));

    $said = lastNotification();

    expect($said['title'])->toBe('Nothing changed')
        ->and($said['body'])->toBe('It was already yours.')
        ->and($said['color'])->toBe('gray');
});

it('answers a conversation the domain will not find with the domain’s one refusal', function (): void {
    // What a record moved to another merchant, or removed between the page load
    // and the click, looks like from here. It is the same answer as a reference
    // that never existed, because a refusal that differs publishes the row.
    $missing = new Conversation();
    $missing->reference = 'csw_nothingatall';

    $outcome = Apply::outcome($missing, fn (): Outcome => Outcome::recorded(1));

    $said = lastNotification();

    expect($outcome)->toBeNull()
        ->and($said['title'])->toBe('Nothing happened')
        ->and($said['body'])->toBe('No such conversation.')
        ->and($said['color'])->toBe('danger');
});

it('returns the outcome for the actions whose answer is not three sentences', function (): void {
    $outcome = Apply::outcome(assigned(), fn (): Outcome => Outcome::recorded(null, 3));

    expect($outcome?->count)->toBe(3)
        // Nothing was said: the caller renders its own sentence about a count.
        ->and(sentNotifications())->toBe([]);
});
