<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Support;

use Liberu\Ecommerce\CustomerServiceWorkspace\Data\Outcome;
use Liberu\Ecommerce\CustomerServiceWorkspace\Data\Timeline;
use Liberu\Ecommerce\CustomerServiceWorkspace\Enums\Author;
use Liberu\Ecommerce\CustomerServiceWorkspace\Enums\ConversationState;
use Liberu\Ecommerce\CustomerServiceWorkspace\Enums\EventKind;
use Liberu\Ecommerce\CustomerServiceWorkspace\Enums\NoteVisibility;
use Liberu\Ecommerce\CustomerServiceWorkspace\Enums\Recording;
use Liberu\Ecommerce\CustomerServiceWorkspace\Enums\RefusalReason;
use Liberu\Ecommerce\CustomerServiceWorkspace\Enums\RequestState;
use Liberu\Ecommerce\CustomerServiceWorkspace\Support\Seams;

/**
 * The sentences this panel is most likely to get wrong, written once.
 *
 * Four of the host's nineteen faults were figures that had drifted from what
 * they claimed to measure, and every one of them read as a healthy number. So
 * nothing here turns an absent figure into a zero: an unmeasured duration, an
 * unrated conversation and a conversation that is not in the queue each render
 * as the fact they are.
 */
final class Render
{
    public const NONE = '—';

    /**
     * Why a write did not happen, in a sentence an operator can act on.
     *
     * A `match` with no default arm over every case the domain publishes: a new
     * refusal reason is a compile-time hole here rather than a blank in a
     * notification, and `RefusalTest` walks `cases()` so the hole is found by
     * the suite rather than by an operator.
     */
    public static function refusal(RefusalReason $reason): string
    {
        return match ($reason) {
            RefusalReason::IllegalTransition => 'the conversation has already moved on, and it cannot make that move from where it is now',
            RefusalReason::ConversationClosed => 'the conversation is closed, so nothing further can be said in it',
            RefusalReason::NotResolved => 'the conversation has not been resolved, and a rating belongs to one that has',
            RefusalReason::ScoreOutOfRange => 'the score is outside the range a rating may take',
            RefusalReason::AuthorMayNotWrite => 'that author may not write this: an agent has to be assigned before replying, and a system note is nobody\'s to write by hand',
            RefusalReason::GatewayUnbound => 'nothing is bound to carry a request to the module that owns this, so the request was recorded and never sent',
            RefusalReason::GatewayUnreachable => 'the owning module could not be reached, so the request is recorded and unanswered',
            RefusalReason::GatewayRefused => 'the owning module refused it',
            RefusalReason::RetentionNotConfigured => 'no retention window is configured, so a retention run has nothing to apply',
        };
    }

    /** What happened, for a caller that has an outcome and needs one sentence about it. */
    public static function outcome(Outcome $outcome, string $did, string $already): string
    {
        return match ($outcome->recording) {
            Recording::Recorded => $did,
            Recording::AlreadyRecorded => $already,
            Recording::Refused => 'Nothing was recorded, because '
                .($outcome->reason === null ? 'the write did not happen' : self::refusal($outcome->reason)).'.',
        };
    }

    /** Recorded, already recorded and refused must not share a colour. */
    public static function outcomeColour(Outcome $outcome): string
    {
        return match ($outcome->recording) {
            Recording::Recorded => 'success',
            Recording::AlreadyRecorded => 'gray',
            Recording::Refused => 'danger',
        };
    }

    /**
     * What became of a request to another module.
     *
     * A refusal here is not "nothing was recorded": the domain persists the
     * request before it transmits, so an unbound or unreachable owner leaves a
     * row behind and the operator has to be told that, or they will ask again.
     */
    public static function actionRequest(Outcome $outcome): string
    {
        return match ($outcome->recording) {
            Recording::Recorded => 'The owning module confirmed it, and the confirmation is on this conversation.',
            Recording::AlreadyRecorded => 'That request had already been made under the same reference, and was not made a second time.',
            Recording::Refused => 'The request is recorded against this conversation and unanswered, because '
                .($outcome->reason === null ? 'the transmission did not happen' : self::refusal($outcome->reason))
                .'. It is on the requests list; nothing was done to the order.',
        };
    }

    /**
     * A place in the queue, or the fact there is not one.
     *
     * `null` from the domain means the conversation is not queued. The `#`
     * prefix is what stops it reading as a count, and the alternative sentence
     * is what stops an absent position reading as first.
     */
    public static function queuePosition(?int $position): string
    {
        return $position === null ? 'Not queued' : '#'.$position;
    }

    /**
     * A measured duration, or the fact that nothing has been measured.
     *
     * `null` is not zero. The host recorded nothing at all for a conversation
     * abandoned in the queue, and a zero here would have read as instant service
     * for exactly the conversations that got none.
     */
    public static function duration(?int $seconds): string
    {
        if ($seconds === null) {
            return self::NONE;
        }

        if ($seconds < 60) {
            return $seconds.'s';
        }

        if ($seconds < 3600) {
            return intdiv($seconds, 60).'m '.($seconds % 60).'s';
        }

        return intdiv($seconds, 3600).'h '.intdiv($seconds % 3600, 60).'m';
    }

    /** A mean the domain could take, or the fact it had nothing to take one over. */
    public static function average(?float $value, int $decimals = 1): string
    {
        return $value === null ? self::NONE : number_format($value, $decimals);
    }

    /** A score out of five, or the fact that nobody gave one. Absence is a fact, not a nought. */
    public static function rating(?int $score): string
    {
        return $score === null ? 'Not rated' : $score.'/5';
    }

    /**
     * The sources the timeline could not ask, by name and with the reason each.
     *
     * An unbound seam and a seam that raised are both `skipped` in the domain's
     * answer, so the binding registry is read here to tell them apart: an
     * operator who has not connected payments needs a different next step from
     * one whose payments module is down.
     */
    public static function skippedSources(Timeline $timeline): string
    {
        if ($timeline->skipped === []) {
            return self::NONE;
        }

        $bindings = Seams::timelineSources();
        $named = [];

        foreach ($timeline->skipped as $name) {
            $named[] = $name.': '.(($bindings[$name] ?? null) === null ? 'not connected' : 'could not be asked');
        }

        return implode('; ', $named);
    }

    /** Which sources answered, so a short timeline is read as short rather than as empty. */
    public static function answeredSources(Timeline $timeline): string
    {
        return $timeline->answered === [] ? self::NONE : implode(', ', $timeline->answered);
    }

    /** What the timeline is, in a sentence: entries, and what could not be asked about. */
    public static function timelineCoverage(Timeline $timeline): string
    {
        $entries = count($timeline->entries).' '.self::plural(count($timeline->entries), 'entry', 'entries')
            .' from '.self::answeredSources($timeline).'.';

        if ($timeline->isComplete()) {
            return $entries.' Every source this workspace knows about answered.';
        }

        return $entries.' Not everything is here: '.self::skippedSources($timeline)
            .'. An unbound source is a gap in this timeline, not an absence of events.';
    }

    public static function timelineColour(Timeline $timeline): string
    {
        return $timeline->isComplete() ? 'success' : 'warning';
    }

    public static function stateLabel(ConversationState $state): string
    {
        return match ($state) {
            ConversationState::Queued => 'Waiting for an agent',
            ConversationState::Assigned => 'With an agent',
            ConversationState::Resolved => 'Resolved',
            ConversationState::Abandoned => 'Nobody ever reached it',
        };
    }

    public static function stateColour(ConversationState $state): string
    {
        return match ($state) {
            ConversationState::Queued => 'warning',
            ConversationState::Assigned => 'info',
            ConversationState::Resolved => 'success',
            // A customer who queued and was never answered. Nothing else on
            // this desk is worse, and the host measured these as nothing.
            ConversationState::Abandoned => 'danger',
        };
    }

    public static function authorLabel(Author $author): string
    {
        return match ($author) {
            Author::Customer => 'Customer',
            Author::Agent => 'Agent',
            Author::System => 'System',
        };
    }

    public static function authorColour(Author $author): string
    {
        return match ($author) {
            Author::Customer => 'info',
            Author::Agent => 'success',
            Author::System => 'gray',
        };
    }

    public static function visibilityLabel(NoteVisibility $visibility): string
    {
        return match ($visibility) {
            NoteVisibility::Internal => 'Internal',
            NoteVisibility::CustomerVisible => 'The customer can read this',
            NoteVisibility::System => 'Written by the workspace',
        };
    }

    public static function visibilityColour(NoteVisibility $visibility): string
    {
        return match ($visibility) {
            NoteVisibility::Internal => 'gray',
            // Published. It cannot be edited away, only corrected by another note.
            NoteVisibility::CustomerVisible => 'warning',
            NoteVisibility::System => 'info',
        };
    }

    public static function eventLabel(EventKind $kind): string
    {
        return match ($kind) {
            EventKind::Opened => 'The customer opened it',
            EventKind::Assigned => 'An agent took it',
            EventKind::Resolved => 'Resolved',
            EventKind::Abandoned => 'Abandoned in the queue',
            EventKind::Rated => 'The customer rated it',
            EventKind::ActionRequested => 'Another module was asked to act',
            EventKind::ActionSettled => 'That module answered',
            EventKind::ParticipantForgotten => 'The participant was erased',
            EventKind::TranscriptRedacted => 'The transcript was redacted',
        };
    }

    public static function requestStateLabel(RequestState $state): string
    {
        return match ($state) {
            RequestState::Requested => 'Asked, no answer yet',
            RequestState::Confirmed => 'Confirmed',
            RequestState::Refused => 'Refused',
        };
    }

    public static function requestStateColour(RequestState $state): string
    {
        return match ($state) {
            // Recorded and unanswered. Somebody has to chase it; it is not pending progress.
            RequestState::Requested => 'warning',
            RequestState::Confirmed => 'success',
            RequestState::Refused => 'danger',
        };
    }

    /** A JSON payload as one readable line, without pretending a nested value is a scalar. */
    public static function payload(mixed $payload): string
    {
        if (! is_array($payload) || $payload === []) {
            return self::NONE;
        }

        $parts = [];

        foreach ($payload as $key => $value) {
            $parts[] = $key.': '.(is_scalar($value) ? (string) $value : self::NONE);
        }

        return implode(', ', $parts);
    }

    public static function plural(int $count, string $one, ?string $many = null): string
    {
        return $count === 1 ? $one : ($many ?? $one.'s');
    }
}
