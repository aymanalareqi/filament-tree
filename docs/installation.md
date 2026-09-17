# Installation and data model

Filament Tree requires PHP 8.3 or newer, Filament 5, and an Eloquent-backed resource.

```bash
composer require alareqi/filament-tree
```

Laravel auto-discovers the service provider. It loads the views and translations and registers the compiled stylesheet with Filament. You do not need to register a panel plugin, add a Vite source, or publish assets.

## Database structure

The table feature expects an adjacency list: every row stores the key of its parent in the same table.

```php
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::create('categories', function (Blueprint $table): void {
    $table->id();
    $table->string('name');
    $table->foreignId('parent_id')
        ->nullable()
        ->constrained('categories')
        ->nullOnDelete();
    $table->unsignedInteger('sort_order')->default(0);
    $table->timestamps();
});
```

- `parent_id` is `null` for roots and contains another category ID for children.
- `sort_order` controls order among siblings. It is only required for reordering.

Eloquent relationships are optional but useful elsewhere:

```php
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

public function parent(): BelongsTo
{
    return $this->belongsTo(self::class, 'parent_id');
}

public function children(): HasMany
{
    return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
}
```

## Custom roots and keys

For a schema that uses a sentinel such as `0` instead of `null`, pass the same value:

```php
->tree(parentColumn: 'parent_id', treeColumn: 'name', rootValue: 0)
```

The database design must permit that value. Integer and string primary keys are supported. Avoid mixing values whose string forms are identical because identity is normalized to strings internally.

Continue with [Tree tables](tree-tables.md), or use only the standalone [Tree select](tree-select.md).
