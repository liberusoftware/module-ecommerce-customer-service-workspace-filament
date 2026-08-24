<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Tests\Fakes;

use Liberu\Ecommerce\CustomerServiceWorkspace\Contracts\ActionGateway;
use Liberu\Ecommerce\CustomerServiceWorkspace\Data\ActionReceipt;
use RuntimeException;

/** Where a safe action would go, if a host had bound anything. */
final class FakeActionGateway implements ActionGateway
{
    public bool $accept = true;

    public bool $throw = false;

    /** @var array<int, array{kind: string, targetRef: string}> */
    public array $submissions = [];

    /** @param  array<string, mixed>  $payload */
    public function submit(string $tenantId, string $kind, string $targetRef, array $payload): ActionReceipt
    {
        $this->submissions[] = ['kind' => $kind, 'targetRef' => $targetRef];

        if ($this->throw) {
            throw new RuntimeException('connection reset');
        }

        return new ActionReceipt($this->accept, 'remote-1', $this->accept ? 'done' : 'no');
    }
}
