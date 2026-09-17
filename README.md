# Filament Tree

Render adjacency-list Eloquent models as expandable, searchable, filterable, and reorderable Filament 5 tables.

## Installation

```bash
composer require alareqi/filament-tree
```

The package service provider is discovered automatically by Laravel. It registers the compiled styles itself, so it does not need panel registration or a custom Filament theme.

## Usage

The page must be a `ListRecords` page using `InteractsWithTreeTable`. Use `TreeColumn` for the column that displays indentation and expand/collapse controls.

```php
use Alareqi\FilamentTree\Columns\TreeColumn;
use Alareqi\FilamentTree\Concerns\InteractsWithTreeTable;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;

class ListCategories extends ListRecords
{
    use InteractsWithTreeTable;
}

public static function table(Table $table): Table
{
    return $table
        ->columns([
            TreeColumn::make('name')->searchable(),
        ])
        ->reorderable('sort_order')
        ->tree(
            parentColumn: 'parent_id',
            treeColumn: 'name',
        );
}
```

The tree works without reordering. To enable ordering, configure Filament's standard `reorderable()` method. The plugin reads Filament's reorder column, direction, condition, authorization, and lifecycle callbacks, but owns the tree drag-and-drop behavior and handle. Use the table's **Enable reordering** action to begin. Drops update an in-memory draft, and no database changes are made until the user clicks **Done**. While dragging, the top and bottom quarters of a row insert the record before or after it, while the highlighted center drops the record inside it as a child. This also allows a leaf record to become a parent. Tree tables intentionally disable pagination. Search and filters include the ancestors of every matching record and disable dragging.

## Tree select field

The package also provides a reusable tree select form field:

```php
use Alareqi\FilamentTree\Forms\Components\TreeSelect;

TreeSelect::make('parent_id')
    ->treeOptions([
        [
            'value' => 1,
            'parent' => null,
            'label' => 'Parent',
        ],
        [
            'value' => 2,
            'parent' => 1,
            'label' => 'Child',
            'description' => 'Optional description',
        ],
    ])
    ->searchable();
```

Each option must contain `value`, `parent`, and `label`. Options may also define `description`, `selectedLabel`, `search`, and `disabled`.

Use Filament's `authorizeReorder()`, `beforeReordering()`, and `afterReordering()` table methods for authorization and lifecycle hooks.

Customize the tree toolbar actions from the `ListRecords` page with typed, IDE-discoverable hooks:

```php
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

public function configureTreeExpandAllAction(Action $action): Action
{
    return $action->icon(Heroicon::PlusCircle);
}

public function configureTreeCollapseAllAction(Action $action): Action
{
    return $action->icon(Heroicon::MinusCircle);
}

public function configureTreeReorderAction(Action $action): Action
{
    return $action->icon(fn ($livewire): Heroicon => $livewire->isTreeReordering()
        ? Heroicon::CheckCircle
        : Heroicon::BarsArrowDown);
}
```

The row icons can also be selected per record from the page:

```php
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

public function getTreeExpandedIcon(Model $record): string | BackedEnum
{
    return Heroicon::OutlinedFolderOpen;
}

public function getTreeCollapsedIcon(Model $record): string | BackedEnum
{
    return Heroicon::OutlinedFolder;
}

public function getTreeLeafIcon(Model $record): string | BackedEnum
{
    return Heroicon::OutlinedDocumentText;
}

public function getTreeExpandIcon(Model $record): string | BackedEnum
{
    return Heroicon::ChevronRight;
}

public function getTreeCollapseIcon(Model $record): string | BackedEnum
{
    return Heroicon::ChevronDown;
}
```
