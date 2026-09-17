# Tree tables

## Set up the list page

Add the concern to the resource's `ListRecords` page—not to the resource or model:

```php
use Alareqi\FilamentTree\Concerns\InteractsWithTreeTable;
use Filament\Resources\Pages\ListRecords;

class ListCategories extends ListRecords
{
    use InteractsWithTreeTable;
}
```

## Configure the table

```php
use Alareqi\FilamentTree\Columns\TreeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;

public static function table(Table $table): Table
{
    return $table
        ->columns([
            TreeColumn::make('name')->searchable(),
            IconColumn::make('is_active')->boolean(),
        ])
        ->tree(
            parentColumn: 'parent_id',
            treeColumn: 'name',
        );
}
```

`TreeColumn` extends Filament's `TextColumn`, so methods such as `label()`, `searchable()`, `formatStateUsing()`, `badge()`, and `description()` remain available. Its normal cell click is disabled because the cell contains tree controls.

## `tree()` options

```php
->tree(
    parentColumn: 'parent_id',
    treeColumn: 'name',
    rootValue: null,
    defaultExpanded: true,
    expandLabel: 'Expand',
    collapseLabel: 'Collapse',
)
```

| Argument | Default | Meaning |
| --- | --- | --- |
| `parentColumn` | `'parent_id'` | Model attribute containing the parent key. |
| `treeColumn` | `'name'` | Column that renders indentation and controls; it must be a `TreeColumn`. |
| `rootValue` | `null` | Parent value identifying root records. |
| `defaultExpanded` | `true` | Whether branches initially appear expanded. |
| `expandLabel` | `'Expand'` | Translatable accessible label for opening a row. |
| `collapseLabel` | `'Collapse'` | Translatable accessible label for closing a row. |

Named arguments are recommended. To start collapsed, set `defaultExpanded: false`.

## Search and filters

Global search, column search, and table filters work normally. The package includes every matching record's ancestors so users can see where the match belongs. Filtered branches begin visible but may be collapsed.

Reordering is disabled while search or filters are active. Changing them also discards an unsaved reorder draft.

## Pagination and ordering

`tree()` disables pagination because the complete hierarchy is needed. Without `reorderable()`, records use primary-key order. With `reorderable('sort_order')`, siblings use that column and then the primary key for stability. Ascending and descending reorder directions are supported.

Other columns, filters, row actions, bulk actions, eager loading, and relationship aggregate columns work normally. The table must use an Eloquent query.

For drag and drop, continue with [Reordering](reordering.md).
