<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Liberu\Ecommerce\CustomerServiceWorkspace\Data\Outcome;
use Liberu\Ecommerce\CustomerServiceWorkspace\Data\Timeline;
use Liberu\Ecommerce\CustomerServiceWorkspace\Data\TimelineEntry;
use Liberu\Ecommerce\CustomerServiceWorkspace\Enums\Author;
use Liberu\Ecommerce\CustomerServiceWorkspace\Enums\ConversationState;
use Liberu\Ecommerce\CustomerServiceWorkspace\Enums\EventKind;
use Liberu\Ecommerce\CustomerServiceWorkspace\Enums\NoteVisibility;
use Liberu\Ecommerce\CustomerServiceWorkspace\Enums\RefusalReason;
use Liberu\Ecommerce\CustomerServiceWorkspace\Enums\RequestState;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Support\Render;

/*
 * The rendering rules, without a panel. Every one of these is a figure the host
 * got wrong somewhere, and every one of the wrong versions read as a healthy
 * number.
 */

it('has a sentence for every refusal the domain can give, and no two are the same', function (): void {
    // A `match` with no default arm: a tenth reason would be an
    // `UnhandledMatchError` in a notification. This walks `cases()` so the hole
    // is found by the suite rather than by an agent mid-refund.
    $sentences = array_map(Render::refusal(...), RefusalReason::cases());

    expect($sentences)->toHaveCount(9)
        ->and(count(array_unique($sentences)))->toBe(9);

    foreach ($sentences as $sentence) {
        expect($sentence)->not->toBe('')
            ->and($sentence)->not->toContain('_');
    }
});

it('never renders an absent queue position as first, or as a count', function (): void {
    // `null` from `QueuePosition` means the conversation is not in the queue.
    // Read as `0` it is a bug an operator cannot see; read as `1` it is a
    // customer jumping the queue.
    expect(Render::queuePosition(null))->toBe('Not queued')
        ->and(Render::queuePosition(1))->toBe('#1')
        ->and(Render::queuePosition(12))->toBe('#12')
        ->and(Render::queuePosition(null))->not->toContain('0')
        ->and(Render::queuePosition(null))->not->toContain('1');
});

it('never renders an unmeasured duration as zero', function (): void {
    // The host recorded nothing at all for a conversation abandoned in the
    // queue, and a zero here would read as instant service for exactly the
    // customers who got none.
    expect(Render::duration(null))->toBe(Render::NONE)
        ->and(Render::duration(0))->toBe('0s')
        ->and(Render::duration(null))->not->toBe(Render::duration(0));
});

it('reads a duration back in the units somebody thinks in', function (): void {
    expect(Render::duration(45))->toBe('45s')
        ->and(Render::duration(125))->toBe('2m 5s')
        ->and(Render::duration(3600))->toBe('1h 0m')
        ->and(Render::duration(4260))->toBe('1h 11m');
});

it('never renders a mean nobody could take as zero', function (): void {
    expect(Render::average(null))->toBe(Render::NONE)
        ->and(Render::average(4.25))->toBe('4.3')
        ->and(Render::average(4.25, 2))->toBe('4.25');
});

it('tells an unrated conversation apart from a badly rated one', function (): void {
    expect(Render::rating(null))->toBe('Not rated')
        ->and(Render::rating(1))->toBe('1/5')
        ->and(Render::rating(5))->toBe('5/5');
});

it('says nothing was skipped when nothing was', function (): void {
    $timeline = new Timeline([], ['notes', 'orders'], []);

    expect(Render::skippedSources($timeline))->toBe(Render::NONE)
        ->and(Render::answeredSources($timeline))->toBe('notes, orders')
        ->and(Render::timelineColour($timeline))->toBe('success')
        ->and(Render::timelineCoverage($timeline))->toContain('Every source this workspace knows about answered');
});

it('counts timeline entries in words that agree with the number', function (): void {
    $one = new Timeline([entry()], ['notes'], []);
    $two = new Timeline([entry(), entry()], ['notes'], []);

    expect(Render::timelineCoverage($one))->toContain('1 entry from notes')
        ->and(Render::timelineCoverage($two))->toContain('2 entries from notes');
});

it('tells recorded, already recorded and refused apart, in words and in colour', function (): void {
    // The host answered "Thank you for your feedback!" to a rating it had
    // silently dropped, because the write returned void.
    $recorded = Outcome::recorded(1);
    $already = Outcome::alreadyRecorded(1);
    $refused = Outcome::refused(RefusalReason::IllegalTransition);

    expect(Render::outcome($recorded, 'It happened.', 'It had happened before.'))->toBe('It happened.')
        ->and(Render::outcome($already, 'It happened.', 'It had happened before.'))->toBe('It had happened before.')
        ->and(Render::outcome($refused, 'It happened.', 'It had happened before.'))
        ->toBe('Nothing was recorded, because '.Render::refusal(RefusalReason::IllegalTransition).'.')
        ->and(Render::outcomeColour($recorded))->toBe('success')
        ->and(Render::outcomeColour($already))->toBe('gray')
        ->and(Render::outcomeColour($refused))->toBe('danger');
});

it('never says nothing was recorded about a request the domain wrote down before transmitting', function (): void {
    // `RequestAction` persists the row, then refuses the transmission. An
    // operator told nothing was recorded asks again, and a retry of a refund is
    // the thing the request reference exists to stop.
    $unbound = Outcome::refused(RefusalReason::GatewayUnbound);

    expect(Render::actionRequest($unbound))->toContain('is recorded against this conversation')
        ->and(Render::actionRequest($unbound))->toContain('nothing was done to the order')
        ->and(Render::actionRequest($unbound))->not->toContain('Nothing was recorded')
        ->and(Render::actionRequest(Outcome::recorded(1)))->toContain('confirmed')
        ->and(Render::actionRequest(Outcome::alreadyRecorded(1)))->toContain('not made a second time');
});

it('falls back to a sentence when a refusal carries no reason', function (): void {
    // `Outcome::refused()` always carries one today. A shape that can express a
    // refusal without a reason is a shape a panel has to be able to render.
    $reasonless = Outcome::recorded(1);

    expect(Render::outcome($reasonless, 'did', 'already'))->toBe('did');
});

it('gives every conversation state a label and a colour, all of them distinct', function (): void {
    $labels = array_map(Render::stateLabel(...), ConversationState::cases());

    expect(count(array_unique($labels)))->toBe(count(ConversationState::cases()))
        // A customer who queued and was never answered is the worst thing on
        // this desk, and the host measured these as nothing at all.
        ->and(Render::stateColour(ConversationState::Abandoned))->toBe('danger')
        ->and(Render::stateColour(ConversationState::Resolved))->toBe('success')
        ->and(Render::stateColour(ConversationState::Queued))->toBe('warning')
        ->and(Render::stateColour(ConversationState::Assigned))->toBe('info');
});

it('gives every author, visibility, event kind and request state a distinct label', function (): void {
    $sets = [
        array_map(Render::authorLabel(...), Author::cases()),
        array_map(Render::authorColour(...), Author::cases()),
        array_map(Render::visibilityLabel(...), NoteVisibility::cases()),
        array_map(Render::visibilityColour(...), NoteVisibility::cases()),
        array_map(Render::eventLabel(...), EventKind::cases()),
        array_map(Render::requestStateLabel(...), RequestState::cases()),
        array_map(Render::requestStateColour(...), RequestState::cases()),
    ];

    foreach ($sets as $labels) {
        expect(count(array_unique($labels)))->toBe(count($labels));
    }
});

it('marks a published note as the irreversible thing it is', function (): void {
    // Publishing a note to the customer cannot be undone; the correction is
    // another note. It does not get the colour of a private one.
    expect(Render::visibilityColour(NoteVisibility::CustomerVisible))->toBe('warning')
        ->and(Render::visibilityLabel(NoteVisibility::CustomerVisible))->toContain('customer can read');
});

it('marks an unanswered request as somebody\'s job rather than as progress', function (): void {
    expect(Render::requestStateColour(RequestState::Requested))->toBe('warning')
        ->and(Render::requestStateColour(RequestState::Confirmed))->toBe('success')
        ->and(Render::requestStateColour(RequestState::Refused))->toBe('danger');
});

it('renders a payload without claiming a nested value is a scalar', function (): void {
    expect(Render::payload(['a' => 1, 'b' => 'two']))->toBe('a: 1, b: two')
        ->and(Render::payload(['a' => ['nested']]))->toBe('a: '.Render::NONE)
        ->and(Render::payload([]))->toBe(Render::NONE)
        ->and(Render::payload('not an array'))->toBe(Render::NONE);
});

it('pluralises with the word somebody would write', function (): void {
    expect(Render::plural(1, 'line'))->toBe('line')
        ->and(Render::plural(2, 'line'))->toBe('lines')
        ->and(Render::plural(1, 'entry', 'entries'))->toBe('entry')
        ->and(Render::plural(0, 'entry', 'entries'))->toBe('entries');
});

function entry(): TimelineEntry
{
    return new TimelineEntry('notes', 'internal', Carbon::parse('2026-08-01 10:00:00'), '1');
}
