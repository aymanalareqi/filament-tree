# Tree select field

`TreeSelect` is a standalone Filament field. It displays flat option data as an expandable hierarchy and stores the selected value.

```php
use Alareqi\FilamentTree\Forms\Components\TreeSelect;

TreeSelect::make('parent_id')
    ->label('Parent')
    ->treeOptions([
        ['value' => 1, 'parent' => null, 'label' => 'Products'],
        ['value' => 2, 'parent' => 1, 'label' => 'Books', 'description' => '12 items'],
    ])
    ->placeholder('No parent');
```

The field is searchable by default. `treeOptions()` accepts an array, an `Arrayable` object such as an Eloquent collection, or a closure:

```php
TreeSelect::make('parent_id')
    ->treeOptions(fn () => Category::query()
        ->orderBy('sort_order')
        ->get()
        ->map(fn (Category $category): array => [
            'value' => $category->id,
            'parent' => $category->parent_id,
            'label' => $category->name,
            'description' => $category->code,
            'disabled' => ! $category->is_active,
        ]));
```

Input order is preserved among siblings.

## Option schema

| Key | Required | Type | Purpose |
| --- | --- | --- | --- |
| `value` | Yes | `int\|string` | Stored value; must be unique. |
| `parent` | Yes | `int\|string\|null` | Parent option value; `null` means root. |
| `label` | Yes | stringable | Main displayed text. |
| `description` | No | stringable/null | Secondary text beside the label. |
| `selectedLabel` | No | stringable | Text displayed when the field is closed. |
| `search` | No | stringable | Custom lowercase search text. |
| `disabled` | No | bool | Prevents selection; children stay navigable. |

Without `selectedLabel`, the field uses the label, or `description — label` when a description exists. Default search text contains the label, description, and value.

## Behavior and configuration

Search keeps matched options and their ancestors visible. Disable it with `->searchable(false)`.

The placeholder stores `null` when placeholder selection is allowed. Configure this through Filament's inherited placeholder APIs. The tree's accessible label defaults to the field label and can be changed with:

```php
->treeLabel('Category hierarchy')
```

When editing, the selected option's ancestors are expanded. To prevent a record from being its own parent, omit or disable it; filter descendants too if your application requires that restriction.

An option with a missing parent is rendered as a root. Cycles cannot recurse forever; unvisited options render once as extra roots. Invalid types, missing required keys, and duplicate values throw `InvalidArgumentException`.
