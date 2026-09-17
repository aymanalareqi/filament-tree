# Troubleshooting and behavior notes

## Configuration exceptions

**“The table must be configured with tree()”** means the page uses the concern but its table does not call `tree()`.

**“Tree column [...] must be an instance of TreeColumn”** means `treeColumn` does not exactly match a `TreeColumn::make()` name.

If controls do not appear, confirm that the resource's `ListRecords` page uses `InteractsWithTreeTable` and the configured column is a `TreeColumn`. No panel plugin or CSS import is needed.

## Missing, detached, or oddly ordered records

A normal root has a parent equal to `rootValue` (`null` by default). A record whose parent is absent from the table query is displayed as an extra root so it stays accessible. If a scope excluded the parent unintentionally, adjust the base query.

Without `reorderable()`, primary-key order is used. For sibling ordering, call `reorderable('sort_order')` before `tree()` and ensure every record has a usable order value.

## Reordering issues

The reorder action is available only when `reorderable()` was configured first, Filament authorization permits it, and no search or filter is active.

Drops are drafts. Click **Done** to persist them. Search or filter changes deliberately discard a draft. Saving may fail if the record set changed, a parent disappeared, a cycle would result, or the user cannot edit a record.

## Expected search behavior

Filtered results include nonmatching ancestors for context, but not nonmatching descendants. This is intentional.

## Pagination and performance

Tree tables always disable pagination. The package loads the base query's complete record set to build the hierarchy, preserve ancestors, and reorder safely. Scope very large trees by a stable boundary such as tenant or workspace, and eager-load relationships used by formatters or actions.

Reorder saves lock and validate the record set, then update parent and order attributes in a transaction.

## Invalid hierarchy data

Rendering tracks visited keys, so cycles do not recurse forever; affected records may appear once as detached roots. Reorder validation rejects cycles, but parent assignments made elsewhere should also be validated.

## TreeSelect exceptions

`TreeSelect` throws `InvalidArgumentException` when an option lacks `value`, `parent`, or `label`; a value has an invalid type; a parent is not `null`, an integer, or a string; or option values are duplicated. Map options into the [documented schema](tree-select.md#option-schema).
