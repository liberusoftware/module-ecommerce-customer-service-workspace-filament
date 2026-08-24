<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Resources\Conversations\Pages;

use Filament\Resources\Pages\ListRecords;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Resources\Conversations\ConversationResource;

/**
 * No header actions. A conversation exists because a customer opened one
 * through `OpenConversation`, which mints its reference and its claim together;
 * a desk that could create one would be minting a customer's proof of standing
 * on the customer's behalf.
 */
final class ListConversations extends ListRecords
{
    protected static string $resource = ConversationResource::class;

    public function mount(): void
    {
        ConversationResource::forgetMeasurements();

        parent::mount();
    }
}
