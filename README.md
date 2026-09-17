# Filament Tree

Display adjacency-list Eloquent models as expandable, searchable, filterable, and reorderable trees in Filament 5. The package also includes a searchable tree-select form field.

## Features

- Expand and collapse individual branches or the entire tree
- Use normal Filament table columns, search, filters, actions, and bulk actions
- Keep matching records visible together with their ancestors while filtering
- Drag records before, after, or inside another record
- Save all reorder changes together in a database transaction
- Select a parent or category with the `TreeSelect` form component
- Support integer and string keys, custom root values, RTL layouts, and dark mode
- Load package styles automatically—no panel plugin or custom theme setup

## Requirements

- PHP 8.3 or later
- Laravel with Eloquent
- Filament 5

## Installation

```bash
composer require alareqi/filament-tree
```

Laravel discovers the service provider automatically. The provider registers the package views, translations, and compiled CSS, so no panel registration or asset publishing is required.

## Quick start

Assume `categories` is an adjacency-list table:

```php
Schema::create('categories', function (Blueprint $table): void {
    $table->id();
    $table->string('name');
    $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
    $table->unsignedInteger('sort_order')->default(0);
    $table->timestamps();
});
```

Add `InteractsWithTreeTable` to the resource's `ListRecords` page:

```php
use Alareqi\FilamentTree\Concerns\InteractsWithTreeTable;
use Filament\Resources\Pages\ListRecords;

class ListCategories extends ListRecords
{
    use InteractsWithTreeTable;
}
```

Then use `TreeColumn` for the visible tree column and call `tree()` after defining the columns:

```php
use Alareqi\FilamentTree\Columns\TreeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

public static function table(Table $table): Table
{
    return $table
        ->columns([
            TreeColumn::make('name')->searchable(),
            TextColumn::make('updated_at')->dateTime()->sortable(),
        ])
        ->reorderable('sort_order')
        ->tree(
            parentColumn: 'parent_id',
            treeColumn: 'name',
        );
}
```

The table starts expanded, root records have a `null` parent, and Filament's **Enable reordering** action starts tree reordering.

> [!IMPORTANT]
> The column named by `treeColumn` must be a `TreeColumn`, and the list page must use `InteractsWithTreeTable`. Call `reorderable()` before `tree()` so the package can read Filament's reorder configuration.

## Tree select quick start

```php
use Alareqi\FilamentTree\Forms\Components\TreeSelect;

TreeSelect::make('parent_id')
    ->label('Parent category')
    ->treeOptions(fn () => Category::query()
        ->orderBy('sort_order')
        ->get()
        ->map(fn (Category $category): array => [
            'value' => $category->getKey(),
            'parent' => $category->parent_id,
            'label' => $category->name,
        ]))
    ->placeholder('No parent')
    ->searchable();
```

The placeholder represents `null` when it is selectable, making this suitable for choosing an optional parent.

## Documentation

- [Installation and data model](docs/installation.md)
- [Tree tables](docs/tree-tables.md)
- [Reordering](docs/reordering.md)
- [Tree select field](docs/tree-select.md)
- [Customization and API reference](docs/customization.md)
- [Troubleshooting and behavior notes](docs/troubleshooting.md)

## License

Filament Tree is open-source software licensed under the [MIT license](LICENSE.md).
