<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Tests\Fakes;

use Illuminate\Support\Carbon;
use Liberu\Ecommerce\CustomerServiceWorkspace\Contracts\TimelineSource;
use Liberu\Ecommerce\CustomerServiceWorkspace\Data\TimelineEntry;
use RuntimeException;

/** A module that holds something. Nothing here talks to one, and nothing in src/ does either. */
final class FakeTimelineSource implements TimelineSource
{
    public bool $throw = false;

    /** @var array<int, array{tenantId: string, subjectKind: string, subjectRef: string}> */
    public array $asked = [];

    public function __construct(private readonly string $name = 'orders') {}

    /** @return array<int, TimelineEntry> */
    public function entriesFor(string $tenantId, string $subjectKind, string $subjectRef): array
    {
        $this->asked[] = ['tenantId' => $tenantId, 'subjectKind' => $subjectKind, 'subjectRef' => $subjectRef];

        if ($this->throw) {
            throw new RuntimeException('connection reset');
        }

        return [new TimelineEntry(
            source: $this->name,
            kind: 'placed',
            occurredAt: Carbon::parse('2026-08-01 10:00:00'),
            reference: 'order-for-'.$subjectRef,
            payload: ['total' => '19.99', 'lines' => ['a']],
        )];
    }
}
