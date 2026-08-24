<?php

declare(strict_types=1);

use Filament\Notifications\Livewire\Notifications;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Liberu\Ecommerce\CustomerServiceWorkspace\Actions\AssignAgent;
use Liberu\Ecommerce\CustomerServiceWorkspace\Actions\OpenConversation;
use Liberu\Ecommerce\CustomerServiceWorkspace\Actions\PostMessage;
use Liberu\Ecommerce\CustomerServiceWorkspace\Actions\ResolveConversation;
use Liberu\Ecommerce\CustomerServiceWorkspace\Enums\Author;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Resources\Conversations\ConversationResource;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Support\PanelAgent;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Support\PanelTenant;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Tests\TestAgent;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Tests\TestCase;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Tests\TestTenant;
use Liberu\Ecommerce\CustomerServiceWorkspace\Models\Conversation;

uses(TestCase::class, RefreshDatabase::class)
    ->beforeEach(function (): void {
        TestTenant::reset();
        TestAgent::reset();

        PanelTenant::resolveUsing(fn (): string => TestTenant::current());
        PanelAgent::resolveUsing(fn (): ?string => TestAgent::current());

        // Load-bearing. Every seam is unbound as the domain ships it, and that
        // unbound state is the behaviour half this suite is about: a test
        // inheriting a binding from the one before it would prove the opposite
        // of what it says.
        Config::set('customer-service-workspace.seams.timeline', [
            'orders' => null,
            'payments' => null,
            'shipments' => null,
            'returns' => null,
        ]);
        Config::set('customer-service-workspace.seams.actions', null);

        // No figure survives a test, for the same reason none survives a page
        // load: a measurement carried past the thing it measured is one somebody
        // reads after it moved.
        ConversationResource::forgetMeasurements();
    })
    ->in(__DIR__.'/Feature');

/** A queued conversation, through the published action. */
function conversation(
    string $tenantId = TestTenant::PRIMARY,
    string $participantRef = 'customer-1',
    string $channel = 'web',
    ?string $name = 'Casey',
    ?string $email = 'casey@example.test',
): Conversation {
    $opened = (new OpenConversation())($tenantId, $channel, $participantRef, $name, $email);

    return Conversation::query()->findOrFail($opened->id);
}

/** A conversation with an agent on it. */
function assigned(?Conversation $conversation = null, string $agentRef = TestAgent::PRIMARY): Conversation
{
    $conversation ??= conversation();
    (new AssignAgent())((string) $conversation->tenant_id, $conversation, $agentRef);

    return $conversation;
}

/** A conversation an agent answered and closed. */
function resolved(?Conversation $conversation = null, string $agentRef = TestAgent::PRIMARY): Conversation
{
    $conversation = assigned($conversation, $agentRef);
    (new PostMessage())((string) $conversation->tenant_id, $conversation, Author::Agent, $agentRef, 'Sorted.');
    (new ResolveConversation())((string) $conversation->tenant_id, $conversation);

    return $conversation;
}

/**
 * The notifications the last request sent, as title, body and colour.
 *
 * Filament's own `assertNotified()` compares the whole serialised notification,
 * so it fails on a duration or an icon this suite has no opinion about. What
 * matters here is which sentence the panel chose and what colour it put on it —
 * a refusal must never be green, and a request that was recorded but not sent
 * must never read as one that was.
 *
 * Reading them consumes them, because mounting the component drains the
 * session: take one copy per assertion.
 *
 * @return array<int, array{title: ?string, body: ?string, color: mixed}>
 */
function sentNotifications(): array
{
    $component = new Notifications();
    $component->mount();

    return $component->notifications
        ->map(fn (Notification $notification): array => [
            'title' => $notification->getTitle(),
            'body' => $notification->getBody(),
            'color' => $notification->getColor(),
        ])
        ->values()
        ->all();
}

/** @return array{title: ?string, body: ?string, color: mixed} */
function lastNotification(): array
{
    $sent = sentNotifications();

    return $sent === [] ? ['title' => null, 'body' => null, 'color' => null] : $sent[count($sent) - 1];
}
