<?php

declare(strict_types=1);

use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Pages\AgentDesk;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Resources\Conversations\Pages\ConversationTimeline;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Resources\Conversations\Pages\ListConversations;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Resources\Conversations\Pages\ViewConversation;
use Livewire\Livewire;

it('boots every screen', function (): void {
    $c = conversation();

    Livewire::test(ListConversations::class)->assertOk();
    Livewire::test(ViewConversation::class, ['record' => $c->reference])->assertOk();
    Livewire::test(ConversationTimeline::class, ['record' => $c->reference])->assertOk();
    Livewire::test(AgentDesk::class)->assertOk();
});
