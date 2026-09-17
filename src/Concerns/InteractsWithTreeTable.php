<?php

namespace Alareqi\FilamentTree\Concerns;

use Alareqi\FilamentTree\TreeConfiguration;
use Alareqi\FilamentTree\TreeTable;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Filters\Indicator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

trait InteractsWithTreeTable
{
    /** @var array<int|string, true> */
    public array $expandedTreeRecords = [];

    /** @var array<int|string, true> */
    public array $collapsedFilteredTreeRecords = [];

    /** @var array<int|string, int> */
    protected array $treeRecordDepths = [];

    /** @var array<int|string, true> */
    protected array $treeRecordsWithChildren = [];

    public bool $treeExpansionInitialized = false;

    public bool $treeReordering = false;

    /** @var array<string, array{parent: mixed, order: int}> */
    public array $pendingTreeRecords = [];

    public function configureTreeExpandAllAction(Action $action): Action
    {
        return $action;
    }

    public function configureTreeCollapseAllAction(Action $action): Action
    {
        return $action;
    }

    public function configureTreeReorderAction(Action $action): Action
    {
        return $action;
    }

    public function getTreeExpandedIcon(Model $record): string|BackedEnum
    {
        return Heroicon::OutlinedFolderOpen;
    }

    public function getTreeCollapsedIcon(Model $record): string|BackedEnum
    {
        return Heroicon::OutlinedFolder;
    }

    public function getTreeLeafIcon(Model $record): string|BackedEnum
    {
        return Heroicon::OutlinedDocumentText;
    }

    public function getTreeExpandIcon(Model $record): string|BackedEnum
    {
        return Heroicon::ChevronRight;
    }

    public function getTreeCollapseIcon(Model $record): string|BackedEnum
    {
        return Heroicon::ChevronDown;
    }

    public function getTreeConfiguration(): TreeConfiguration
    {
        return TreeTable::configuration($this->getTable());
    }

    public function getTableRecords(): Collection
    {
        if ($this->cachedTableRecords instanceof Collection) {
            return $this->cachedTableRecords;
        }

        $configuration = $this->getTreeConfiguration();
        $query = $this->getTable()->getQuery();

        if ($query === null) {
            throw ValidationException::withMessages([
                'tree' => 'Tree tables require an Eloquent query.',
            ]);
        }

        foreach ($this->getTable()->getVisibleColumns() as $column) {
            $column->applyRelationshipAggregates($query);
            $column->applyEagerLoading($query);
        }

        $orderColumn = $configuration->orderColumn;
        $allRecordsQuery = $query->orderBy($query->getModel()->getQualifiedKeyName());

        if (filled($orderColumn)) {
            $allRecordsQuery
                ->reorder()
                ->orderBy($configuration->parentColumn)
                ->orderBy($orderColumn, $configuration->orderDirection)
                ->orderBy($query->getModel()->getQualifiedKeyName());
        }

        $allRecords = $allRecordsQuery->get();

        if ($this->treeReordering) {
            foreach ($allRecords as $record) {
                $pendingRecord = $this->pendingTreeRecords[(string) $record->getKey()] ?? null;

                if ($pendingRecord === null) {
                    continue;
                }

                $record->setAttribute($configuration->parentColumn, $pendingRecord['parent']);
                $record->setAttribute($orderColumn, $pendingRecord['order']);
            }
        }

        $recordsByKey = $allRecords->keyBy(fn (Model $record): string => (string) $record->getKey());
        $isFiltered = $this->isTreeFiltered();
        $includedKeys = $isFiltered
            ? $this->getFilteredTreeRecordKeys($recordsByKey, $configuration)
            : $recordsByKey->mapWithKeys(fn (Model $record): array => [(string) $record->getKey() => true])->all();

        $childrenByParent = $allRecords
            ->filter(fn (Model $record): bool => isset($includedKeys[(string) $record->getKey()]))
            ->groupBy(fn (Model $record): string => $this->normalizeTreeKey($record->getAttribute($configuration->parentColumn)))
            ->map(fn (Collection $siblings): Collection => $siblings
                ->sortBy(
                    fn (Model $record): mixed => $record->getAttribute($orderColumn),
                    descending: $configuration->orderDirection === 'desc',
                )
                ->values());

        $this->treeRecordsWithChildren = [];

        foreach ($childrenByParent as $parentKey => $children) {
            if (($parentKey !== $this->normalizeTreeKey($configuration->rootValue)) && $children->isNotEmpty()) {
                $this->treeRecordsWithChildren[$parentKey] = true;
            }
        }

        if (! $this->treeExpansionInitialized) {
            $this->expandedTreeRecords = $configuration->defaultExpanded
                ? $this->treeRecordsWithChildren
                : [];
            $this->treeExpansionInitialized = true;
        }

        $flattened = [];
        $visited = [];
        $rootKey = $this->normalizeTreeKey($configuration->rootValue);

        foreach ($childrenByParent->get($rootKey, collect()) as $rootRecord) {
            $this->appendTreeRecord($rootRecord, 0, $childrenByParent, $isFiltered, $flattened, $visited);
        }

        foreach ($allRecords as $record) {
            $recordKey = (string) $record->getKey();
            $parentKey = $this->normalizeTreeKey($record->getAttribute($configuration->parentColumn));

            if (
                isset($includedKeys[$recordKey])
                && ! isset($visited[$recordKey])
                && ($parentKey !== $rootKey)
                && ! isset($includedKeys[$parentKey])
            ) {
                $this->appendTreeRecord($record, 0, $childrenByParent, $isFiltered, $flattened, $visited);
            }
        }

        /** @var EloquentCollection<int|string, Model> $records */
        $records = $query->getModel()->newCollection($flattened)->keyBy(fn (Model $record): string => (string) $record->getKey());

        return $this->cachedTableRecords = $records;
    }

    public function toggleTreeRecord(int|string $recordKey): void
    {
        $key = (string) $recordKey;
        $this->flushCachedTableRecords();
        $this->getTableRecords();

        if (! isset($this->treeRecordsWithChildren[$key])) {
            return;
        }

        if ($this->isTreeFiltered()) {
            if (isset($this->collapsedFilteredTreeRecords[$key])) {
                unset($this->collapsedFilteredTreeRecords[$key]);
            } else {
                $this->collapsedFilteredTreeRecords[$key] = true;
            }
        } elseif (isset($this->expandedTreeRecords[$key])) {
            unset($this->expandedTreeRecords[$key]);
        } else {
            $this->expandedTreeRecords[$key] = true;
        }

        $this->flushCachedTableRecords();
    }

    public function expandAllTreeRecords(): void
    {
        $this->getTableRecords();

        if ($this->isTreeFiltered()) {
            $this->collapsedFilteredTreeRecords = [];
        } else {
            $this->expandedTreeRecords = $this->treeRecordsWithChildren;
        }

        $this->flushCachedTableRecords();
    }

    public function collapseAllTreeRecords(): void
    {
        $this->getTableRecords();

        if ($this->isTreeFiltered()) {
            $this->collapsedFilteredTreeRecords = $this->treeRecordsWithChildren;
        } else {
            $this->expandedTreeRecords = [];
        }

        $this->treeExpansionInitialized = true;
        $this->flushCachedTableRecords();
    }

    public function getTreeRecordDepth(Model $record): int
    {
        return $this->treeRecordDepths[(string) $record->getKey()] ?? 0;
    }

    public function treeRecordHasChildren(Model $record): bool
    {
        return isset($this->treeRecordsWithChildren[(string) $record->getKey()]);
    }

    public function isTreeRecordExpanded(Model $record): bool
    {
        if ($this->isTreeFiltered()) {
            return ! isset($this->collapsedFilteredTreeRecords[(string) $record->getKey()]);
        }

        return isset($this->expandedTreeRecords[(string) $record->getKey()]);
    }

    public function canReorderTree(): bool
    {
        return $this->getTreeConfiguration()->reorderable
            && ! $this->isTreeFiltered();
    }

    public function isTreeReordering(): bool
    {
        return $this->treeReordering;
    }

    public function isTableReordering(): bool
    {
        return false;
    }

    public function toggleTreeReordering(): void
    {
        if ($this->treeReordering) {
            $this->saveTreeReordering();

            return;
        }

        $this->startTreeReordering();
    }

    public function startTreeReordering(): void
    {
        if (! $this->canReorderTree()) {
            return;
        }

        $configuration = $this->getTreeConfiguration();
        $query = $this->getTable()->getQuery();

        if ($query === null || blank($configuration->orderColumn)) {
            return;
        }

        $this->pendingTreeRecords = $query
            ->get()
            ->mapWithKeys(fn (Model $record): array => [(string) $record->getKey() => [
                'parent' => $record->getAttribute($configuration->parentColumn),
                'order' => (int) $record->getAttribute($configuration->orderColumn),
            ]])
            ->all();
        $this->treeReordering = true;
        $this->expandAllTreeRecords();
        $this->flushCachedTableRecords();
    }

    public function stageTreeRecordDrop(
        int|string $draggedRecordKey,
        int|string $targetRecordKey,
        string $position,
    ): void {
        if (! $this->treeReordering || ! $this->canReorderTree() || ! in_array($position, ['before', 'inside', 'after'], true)) {
            return;
        }

        $configuration = $this->getTreeConfiguration();
        $draggedKey = (string) $draggedRecordKey;
        $targetKey = (string) $targetRecordKey;

        if ($draggedKey === $targetKey || ! isset($this->pendingTreeRecords[$draggedKey], $this->pendingTreeRecords[$targetKey])) {
            throw ValidationException::withMessages(['tree' => __('The selected drop target is invalid.')]);
        }

        $destinationParent = $position === 'inside'
            ? $targetRecordKey
            : $this->pendingTreeRecords[$targetKey]['parent'];

        for ($ancestorKey = $this->normalizeTreeKey($destinationParent); isset($this->pendingTreeRecords[$ancestorKey]);) {
            if ($ancestorKey === $draggedKey) {
                throw ValidationException::withMessages([
                    'tree' => __('A record cannot be moved below itself or one of its descendants.'),
                ]);
            }

            $ancestorKey = $this->normalizeTreeKey($this->pendingTreeRecords[$ancestorKey]['parent']);
        }

        $sourceParent = $this->pendingTreeRecords[$draggedKey]['parent'];
        $this->pendingTreeRecords[$draggedKey]['parent'] = $destinationParent;
        $parentKeys = collect([$sourceParent, $destinationParent])
            ->unique(fn (mixed $key): string => $this->normalizeTreeKey($key));

        foreach ($parentKeys as $parentKey) {
            $siblings = collect($this->pendingTreeRecords)
                ->filter(fn (array $record): bool => $this->normalizeTreeKey($record['parent']) === $this->normalizeTreeKey($parentKey))
                ->sortBy('order', descending: $configuration->orderDirection === 'desc')
                ->keys()
                ->reject(fn (string $key): bool => $key === $draggedKey)
                ->values();

            if ($this->normalizeTreeKey($parentKey) === $this->normalizeTreeKey($destinationParent)) {
                $targetIndex = $siblings->search($targetKey);
                $insertionIndex = match ($position) {
                    'before' => $targetIndex === false ? $siblings->count() : $targetIndex,
                    'after' => $targetIndex === false ? $siblings->count() : $targetIndex + 1,
                    default => $siblings->count(),
                };
                $siblings->splice($insertionIndex, 0, [$draggedKey]);
            }

            foreach ($siblings as $index => $siblingKey) {
                $this->pendingTreeRecords[$siblingKey]['order'] = $configuration->orderDirection === 'desc'
                    ? $siblings->count() - $index
                    : $index + 1;
            }
        }

        if ($position === 'inside') {
            $this->expandedTreeRecords[$targetKey] = true;
        }

        $this->flushCachedTableRecords();
    }

    public function saveTreeReordering(): void
    {
        if (! $this->treeReordering || ! $this->canReorderTree()) {
            return;
        }

        $table = $this->getTable();
        $configuration = $this->getTreeConfiguration();
        $orderColumn = $configuration->orderColumn;
        $query = $table->getQuery();

        if (blank($orderColumn) || $query === null) {
            return;
        }

        $order = collect($this->getTableRecords())->keys()->all();
        $table->callBeforeReordering($order);

        DB::transaction(function () use ($configuration, $orderColumn, $query): void {
            $recordsByKey = $query
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (Model $record): string => (string) $record->getKey());

            if ($recordsByKey->count() !== count($this->pendingTreeRecords)) {
                throw ValidationException::withMessages(['tree' => __('The tree changed while it was being reordered. Please try again.')]);
            }

            foreach ($this->pendingTreeRecords as $recordKey => $pendingRecord) {
                $record = $recordsByKey->get($recordKey);
                $parentKey = $this->normalizeTreeKey($pendingRecord['parent']);

                if (! $record instanceof Model || ! static::getResource()::canEdit($record)) {
                    abort(403);
                }

                if ($parentKey !== $this->normalizeTreeKey($configuration->rootValue) && ! $recordsByKey->has($parentKey)) {
                    throw ValidationException::withMessages(['tree' => __('The selected parent is invalid.')]);
                }

                $visitedKeys = [$recordKey => true];

                while (isset($this->pendingTreeRecords[$parentKey])) {
                    if (isset($visitedKeys[$parentKey])) {
                        throw ValidationException::withMessages([
                            'tree' => __('A record cannot be moved below itself or one of its descendants.'),
                        ]);
                    }

                    $visitedKeys[$parentKey] = true;
                    $parentKey = $this->normalizeTreeKey($this->pendingTreeRecords[$parentKey]['parent']);
                }

                $query->clone()->whereKey($record->getKey())->update([
                    $configuration->parentColumn => $pendingRecord['parent'],
                    $orderColumn => $pendingRecord['order'],
                ]);
            }
        });

        $table->callAfterReordering($order);
        $this->treeReordering = false;
        $this->pendingTreeRecords = [];
        $this->flushCachedTableRecords();
    }

    public function updatingTableSearch(): void
    {
        $this->discardTreeReordering();
        $this->collapsedFilteredTreeRecords = [];
    }

    public function updatingTableColumnSearches(): void
    {
        $this->discardTreeReordering();
        $this->collapsedFilteredTreeRecords = [];
    }

    public function updatingTableFilters(): void
    {
        $this->discardTreeReordering();
        $this->collapsedFilteredTreeRecords = [];
    }

    private function discardTreeReordering(): void
    {
        $this->treeReordering = false;
        $this->pendingTreeRecords = [];
        $this->flushCachedTableRecords();
    }

    /** @param Collection<int|string, Model> $recordsByKey */
    private function getFilteredTreeRecordKeys(Collection $recordsByKey, TreeConfiguration $configuration): array
    {
        $matchingKeys = $this->getFilteredTableQuery()
            ?->reorder()
            ->pluck($recordsByKey->first()?->getKeyName() ?? 'id')
            ->map(fn (mixed $key): string => (string) $key)
            ->all() ?? [];
        $included = [];

        foreach ($matchingKeys as $matchingKey) {
            $current = $recordsByKey->get($matchingKey);

            while ($current instanceof Model && ! isset($included[(string) $current->getKey()])) {
                $included[(string) $current->getKey()] = true;
                $current = $recordsByKey->get((string) $current->getAttribute($configuration->parentColumn));
            }
        }

        return $included;
    }

    /**
     * @param  Collection<int, Model>  $records
     * @return array<string, true>
     */
    private function getTreeDescendantKeys(Model $record, Collection $records, TreeConfiguration $configuration): array
    {
        $childrenByParent = $records->groupBy(
            fn (Model $item): string => $this->normalizeTreeKey($item->getAttribute($configuration->parentColumn)),
        );
        $descendantKeys = [];
        $pendingKeys = [(string) $record->getKey()];

        while ($pendingKeys !== []) {
            $parentKey = array_pop($pendingKeys);

            foreach ($childrenByParent->get($parentKey, collect()) as $child) {
                $childKey = (string) $child->getKey();

                if (isset($descendantKeys[$childKey])) {
                    continue;
                }

                $descendantKeys[$childKey] = true;
                $pendingKeys[] = $childKey;
            }
        }

        return $descendantKeys;
    }

    private function isTreeFiltered(): bool
    {
        if (filled($this->getTableSearch())) {
            return true;
        }

        if (collect($this->getTableColumnSearches())->contains(fn (mixed $search): bool => filled($search))) {
            return true;
        }

        return collect($this->getTable()->getFilterIndicators())
            ->contains(fn (Indicator $indicator): bool => filled($indicator->getLabel()));
    }

    private function appendTreeRecord(
        Model $record,
        int $depth,
        Collection $childrenByParent,
        bool $isFiltered,
        array &$flattened,
        array &$visited,
    ): void {
        $recordKey = (string) $record->getKey();

        if (isset($visited[$recordKey])) {
            return;
        }

        $visited[$recordKey] = true;
        $this->treeRecordDepths[$recordKey] = $depth;
        $flattened[] = $record;

        if (
            ($isFiltered && isset($this->collapsedFilteredTreeRecords[$recordKey]))
            || (! $isFiltered && ! isset($this->expandedTreeRecords[$recordKey]))
        ) {
            return;
        }

        foreach ($childrenByParent->get($recordKey, collect()) as $child) {
            $this->appendTreeRecord($child, $depth + 1, $childrenByParent, $isFiltered, $flattened, $visited);
        }
    }

    private function normalizeTreeKey(mixed $key): string
    {
        return $key === null ? '__filament_tree_root__' : (string) $key;
    }
}
