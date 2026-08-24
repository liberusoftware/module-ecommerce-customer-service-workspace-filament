{{-- A table on a page rather than a resource: the rows come from the domain
     query that names the work, and the queue is the ordering, not a column. --}}
<x-filament-panels::page>
    {{ $this->table }}
</x-filament-panels::page>
