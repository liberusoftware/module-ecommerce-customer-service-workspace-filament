<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Support;

use Closure;
use Filament\Notifications\Notification;
use Liberu\Ecommerce\CustomerServiceWorkspace\Data\Outcome;
use Liberu\Ecommerce\CustomerServiceWorkspace\Exceptions\CustomerServiceWorkspaceException;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Resources\Conversations\ConversationResource;
use Liberu\Ecommerce\CustomerServiceWorkspace\Models\Conversation;
use Liberu\Ecommerce\CustomerServiceWorkspace\Queries\FindConversation;

/**
 * How every write on this desk happens: re-read the conversation, let the
 * domain decide, and say which of the three things it decided.
 *
 * The re-read is the point. A control the panel hid is not a control — the
 * screen that offered the button and the conversation the domain guards are two
 * copies of the same fact, and only one of them is current. So the visibility
 * rules exist to stop an agent asking for a move that cannot be made, and this
 * exists because asking anyway has to be refused rather than done.
 *
 * The host had neither half: `ChatService::assignAgent` guarded nothing, and
 * the panel page that also assigned checked a different condition inline.
 */
final class Apply
{
    /**
     * @param  Closure(Conversation, string): Outcome  $act  the domain action, given the conversation as it is now
     */
    public static function to(Conversation $conversation, string $done, string $did, string $already, Closure $act): void
    {
        $outcome = self::outcome($conversation, $act);

        if ($outcome !== null) {
            self::report($outcome, $done, $did, $already);
        }
    }

    /**
     * The same re-read, for the two actions whose result is not three sentences.
     *
     * `null` means the domain would not find the conversation at all, and the
     * refusal has already been rendered.
     *
     * @param  Closure(Conversation, string): Outcome  $act
     */
    public static function outcome(Conversation $conversation, Closure $act): ?Outcome
    {
        $tenant = PanelTenant::current();

        try {
            $outcome = $act((new FindConversation())($tenant, $conversation->reference), $tenant);
        } catch (CustomerServiceWorkspaceException $e) {
            // The domain's one refusal for a reference that is somebody else's
            // and one that does not exist. Repeating it is not publishing it.
            Notification::make()
                ->title('Nothing happened')
                ->body($e->getMessage())
                ->color('danger')
                ->persistent()
                ->send();

            return null;
        }

        ConversationResource::forgetMeasurements();

        return $outcome;
    }

    public static function report(Outcome $outcome, string $done, string $did, string $already): void
    {
        Notification::make()
            ->title(match (true) {
                $outcome->happened() => $done,
                $outcome->wasRefused() => 'Refused',
                default => 'Nothing changed',
            })
            ->body(Render::outcome($outcome, $did, $already))
            ->color(Render::outcomeColour($outcome))
            ->persistent()
            ->send();
    }
}
