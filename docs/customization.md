# Customization and API reference

Override these typed hooks on the `ListRecords` page that uses `InteractsWithTreeTable`.

## Toolbar actions

```php
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

public function configureTreeExpandAllAction(Action $action): Action
{
    return $action->icon(Heroicon::PlusCircle)->tooltip('Expand all');
}

public function configureTreeCollapseAllAction(Action $action): Action
{
    return $action->icon(Heroicon::MinusCircle)->tooltip('Collapse all');
}

public function configureTreeReorderAction(Action $action): Action
{
    return $action->icon(fn ($livewire): Heroicon => $livewire->isTreeReordering()
        ? Heroicon::CheckCircle
        : Heroicon::BarsArrowDown);
}
```

Always return the action. The first two configure the icon-button toolbar actions; the third configures Filament's reorder trigger.

## Per-record icons

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
    return $record->is_active
        ? Heroicon::OutlinedDocumentText
        : Heroicon::OutlinedArchiveBox;
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

Filament icon strings and backed icon enums are supported.

## Translations

English and Arabic translations are included for **Expand all** and **Collapse all**. Override them under `lang/vendor/filament-tree/{locale}/tree.php` using Laravel's normal vendor translation workflow.

Individual-row labels come from `expandLabel` and `collapseLabel`; literal text and translation keys work because they pass through `__()`.

## Advanced helpers

| Method | Result |
| --- | --- |
| `getTreeConfiguration()` | Current immutable configuration |
| `getTreeRecordDepth(Model $record)` | Zero-based visible depth |
| `treeRecordHasChildren(Model $record)` | Whether included children exist |
| `isTreeRecordExpanded(Model $record)` | Current expansion state |
| `canReorderTree()` | Whether reordering is configured and unfiltered |
| `isTreeReordering()` | Whether a reorder draft is active |
| `expandAllTreeRecords()` | Expands all included parents |
| `collapseAllTreeRecords()` | Collapses all included parents |
| `toggleTreeRecord(int|string $key)` | Toggles one parent |

Drop-staging and saving methods are intended for the package UI and normally should not be called directly.
