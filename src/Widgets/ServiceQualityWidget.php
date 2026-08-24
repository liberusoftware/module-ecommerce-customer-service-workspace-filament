<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Liberu\Ecommerce\CustomerServiceWorkspace\Data\ServiceSummary;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Support\PanelTenant;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Support\Render;
use Liberu\Ecommerce\CustomerServiceWorkspace\Queries\MeasureService;

/**
 * This merchant's service quality, as the domain measures it.
 *
 * Four of the host's faults were counters here: a response time written at
 * assignment before anybody had replied, a resolution time that excluded the
 * whole queue wait, nothing at all recorded for a conversation abandoned in the
 * queue, and three message counters that did not decompose. Every figure on this
 * widget is a subtraction the domain takes over recorded timestamps, and a mean
 * it had nothing to take renders as unmeasured rather than as nought — a zero
 * here would read as instant service for the customers who got none.
 *
 * The conversations still open are excluded from every mean and counted out
 * loud, so a good-looking average is never a small sample in disguise.
 */
final class ServiceQualityWidget extends StatsOverviewWidget
{
    protected ?string $heading = 'Service quality';

    protected ?string $description = 'Over every conversation this merchant has. Each duration runs from the moment the customer arrived, so the queue wait is inside all three.';

    protected int|array|null $columns = 4;

    /** A panel with no merchant has no service quality to summarise. */
    public static function canView(): bool
    {
        return PanelTenant::resolvable();
    }

    /** @return array<int, Stat> */
    protected function getStats(): array
    {
        $summary = $this->summary();

        return [
            Stat::make('Mean wait', Render::duration($summary->averageWaitSeconds))
                ->description($summary->averageWaitSeconds === null
                    ? 'Nothing has been waited through to an end yet.'
                    : 'Arrival until an agent took it, or until it was given up on.')
                ->color($summary->averageWaitSeconds === null ? 'gray' : 'primary'),

            Stat::make('Mean first reply', Render::duration($summary->averageFirstReplySeconds))
                ->description($summary->averageFirstReplySeconds === null
                    ? 'No agent has replied to a closed conversation yet.'
                    : 'Arrival until an agent actually said something.')
                ->color($summary->averageFirstReplySeconds === null ? 'gray' : 'primary'),

            Stat::make('Mean resolution', Render::duration($summary->averageResolutionSeconds))
                ->description($summary->measured.' measured, '.$summary->unmeasured.' still open and left out of every mean.')
                ->color($summary->averageResolutionSeconds === null ? 'gray' : 'primary'),

            Stat::make('Mean rating', Render::average($summary->averageRating))
                ->description($summary->rated === 0
                    ? 'Nobody has rated a conversation.'
                    : 'Over '.$summary->rated.' rated '.Render::plural($summary->rated, 'conversation').'.')
                ->color($summary->averageRating === null ? 'gray' : 'success'),

            Stat::make('Never reached', (string) $summary->abandoned)
                ->description($summary->abandoned === 0
                    ? 'Every conversation that closed had somebody on it.'
                    : 'Queued and never answered. The host recorded nothing at all for these, so the customers who got no service were the ones missing from its numbers.')
                ->color($summary->abandoned === 0 ? 'success' : 'danger'),
        ];
    }

    /** ponytail: one summary per render, memoised because five stats read it and it walks the merchant's conversations. */
    private ?ServiceSummary $summary = null;

    private function summary(): ServiceSummary
    {
        return $this->summary ??= (new MeasureService())->across(PanelTenant::current());
    }
}
