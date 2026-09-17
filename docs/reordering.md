# Reordering

Tree rendering does not require ordering. To let users rearrange records, call Filament's `reorderable()` before `tree()`:

```php
return $table
    ->columns([TreeColumn::make('name')])
    ->reorderable('sort_order')
    ->tree(parentColumn: 'parent_id', treeColumn: 'name');
```

The package reads Filament's reorder column, direction, condition, authorization, and callbacks when `tree()` runs.

## User workflow

1. Click **Enable reordering**.
2. Drag a row by its handle.
3. Drop on the top quarter to place it before the target.
4. Drop on the highlighted center to make it a child of the target.
5. Drop on the bottom quarter to place it after the target.
6. Click **Done** to save the draft.

A center drop can turn a leaf into a parent. All branches expand when reordering starts.

## Safety and persistence

Moves update a Livewire draft and do not immediately write to the database. **Done** validates and saves all parent and sibling-order values in one transaction. The package prevents self-descendant moves and verifies that:

- the record set did not change while reordering;
- every non-root parent exists;
- the submitted hierarchy contains no cycle; and
- the resource user can edit every record.

Changing search or filters discards the draft.

## Authorization and callbacks

Use Filament's standard APIs:

```php
->reorderable('sort_order')
->authorizeReorder(fn (): bool => auth()->user()->can('reorder_categories'))
->beforeReordering(function (array $order): void {
    // Before the save transaction.
})
->afterReordering(function (array $order): void {
    // After a successful transaction.
})
->tree(parentColumn: 'parent_id', treeColumn: 'name')
```

`$order` is the flattened list of record keys in visible tree order. Saving also requires the resource's `canEdit()` check for every record.

For descending reorder direction, larger order values are assigned to earlier siblings, matching the configured display order.
