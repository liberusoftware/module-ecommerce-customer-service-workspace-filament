<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Resources\Conversations\Pages;

use BackedEnum;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Liberu\Ecommerce\CustomerServiceWorkspace\Data\Timeline;
use Liberu\Ecommerce\CustomerServiceWorkspace\Data\TimelineEntry;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Resources\Conversations\ConversationResource;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Support\PanelTenant;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Support\Render;
use Liberu\Ecommerce\CustomerServiceWorkspace\Models\Conversation;
use Liberu\Ecommerce\CustomerServiceWorkspace\Queries\AssembleTimeline;

/**
 * What this customer has done, from whichever modules hold it.
 *
 * Assembled on read and stored nowhere. The subject asked about is the
 * participant, not the conversation: orders, payments, shipments and returns
 * can key on a customer, and this module holds no mapping from a conversation
 * to an order — an agent who wants one names it on the request form.
 *
 * A source nobody bound is named, by name and with the reason, rather than
 * counted as a source that answered nothing. An empty timeline with three
 * unbound seams is not a customer who has done nothing.
 */
final class ConversationTimeline extends Page implements HasTable
{
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = ConversationResource::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static ?string $title = 'What this customer has done';

    protected string $view = 'ecommerce-customer-service-workspace-filament::pages.conversation-timeline';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getSubheading(): string
    {
        if ($this->forgotten()) {
            return 'This participant has been erased, so there is no subject to ask anybody about. '
                .'The conversation, its timings and its counts are still here; the person is not.';
        }

        return Render::timelineCoverage($this->timeline());
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): Collection => $this->rows())
            ->columns([
                TextColumn::make('occurred_at')->label('When'),
                TextColumn::make('source')->label('Who holds it')->badge(),
                TextColumn::make('kind')->label('What happened'),
                TextColumn::make('reference')->label('Reference')->copyable(),
                TextColumn::make('detail')->label('Detail')->wrap(),
            ])
            ->paginated(false)
            ->filters([])
            // Array records: the row URL and action are typed against a Model.
            ->recordUrl(null)
            ->recordAction(null)
            ->recordActions([])
            ->headerActions([])
            ->toolbarActions([])
            ->emptyStateIcon('heroicon-o-clock')
            ->emptyStateHeading($this->emptyHeading())
            ->emptyStateDescription($this->emptyDescription());
    }

    /** @return Collection<int, array{occurred_at: string, source: string, kind: string, reference: string, detail: string}> */
    private function rows(): Collection
    {
        return collect($this->timeline()->entries)
            ->map(fn (TimelineEntry $entry): array => [
                'occurred_at' => $entry->occurredAt->toDayDateTimeString(),
                'source' => $entry->source,
                'kind' => $entry->kind,
                'reference' => $entry->reference,
                'detail' => Render::payload($entry->payload),
            ])
            ->values();
    }

    /** ponytail: one assembly per request, memoised because the subheading, the table and both empty-state sentences read it. */
    private ?Timeline $timeline = null;

    private function timeline(): Timeline
    {
        return $this->timeline ??= $this->forgotten()
            ? new Timeline([], [], [])
            : (new AssembleTimeline())(PanelTenant::current(), 'participant', $this->conversation()->participant_ref);
    }

    private function emptyHeading(): string
    {
        if ($this->forgotten()) {
            return 'There is nobody to ask about';
        }

        return $this->timeline()->isComplete()
            ? 'Nothing has happened to this customer anywhere'
            : 'Nothing here, and not everything was asked';
    }

    private function emptyDescription(): string
    {
        if ($this->forgotten()) {
            return 'The participant was erased. Asking another module about a redacted reference would be asking about the wrong person.';
        }

        return $this->timeline()->isComplete()
            ? 'Every source this workspace knows about answered, and none of them holds anything for this customer.'
            : 'These sources were not asked and this list is not a picture of nothing: '.Render::skippedSources($this->timeline()).'.';
    }

    private function forgotten(): bool
    {
        return $this->conversation()->forgotten_at !== null;
    }

    private function conversation(): Conversation
    {
        /** @var Conversation $record */
        $record = $this->getRecord();

        return $record;
    }
}
