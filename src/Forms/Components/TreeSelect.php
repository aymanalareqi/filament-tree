<?php

namespace Alareqi\FilamentTree\Forms\Components;

use Closure;
use Filament\Forms\Components\Concerns\CanBeSearchable;
use Filament\Forms\Components\Concerns\CanSelectPlaceholder;
use Filament\Forms\Components\Concerns\HasPlaceholder;
use Filament\Forms\Components\Field;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;
use InvalidArgumentException;

class TreeSelect extends Field
{
    use CanBeSearchable;
    use CanSelectPlaceholder;
    use HasPlaceholder;

    protected string $view = 'filament-tree::forms.components.tree-select';

    /**
     * @var array<int, array<string, mixed>> | Arrayable<int, array<string, mixed>> | Closure
     */
    protected array|Arrayable|Closure $treeOptions = [];

    protected string|Closure|null $treeLabel = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->searchable();
        $this->placeholder(__('Select an option'));
    }

    /**
     * Each option must contain `value`, `parent`, and `label`. It may also contain
     * `description`, `selectedLabel`, `search`, and `disabled`.
     *
     * @param  array<int, array<string, mixed>> | Arrayable<int, array<string, mixed>> | Closure  $options
     */
    public function treeOptions(array|Arrayable|Closure $options): static
    {
        $this->treeOptions = $options;

        return $this;
    }

    public function treeLabel(string|Closure|null $label): static
    {
        $this->treeLabel = $label;

        return $this;
    }

    public function getTreeLabel(): string
    {
        return (string) ($this->evaluate($this->treeLabel) ?? $this->getLabel());
    }

    /**
     * @return array<int, array{
     *     value: int|string,
     *     parentValue: int|string|null,
     *     label: string,
     *     description: string|null,
     *     selectedLabel: string,
     *     depth: int,
     *     hasChildren: bool,
     *     ancestorValues: array<int, int|string>,
     *     search: string,
     *     disabled: bool
     * }>
     */
    public function getTreeOptions(): array
    {
        $rawOptions = $this->evaluate($this->treeOptions);
        $rawOptions = $rawOptions instanceof Arrayable ? $rawOptions->toArray() : $rawOptions;
        $options = [];

        foreach ($rawOptions as $option) {
            if (! is_array($option) || ! array_key_exists('value', $option) || ! array_key_exists('parent', $option) || ! array_key_exists('label', $option)) {
                throw new InvalidArgumentException('Tree select options must contain value, parent, and label keys.');
            }

            $value = $option['value'];

            if (! is_int($value) && ! is_string($value)) {
                throw new InvalidArgumentException('Tree select option values must be integers or strings.');
            }

            if ($option['parent'] !== null && ! is_int($option['parent']) && ! is_string($option['parent'])) {
                throw new InvalidArgumentException('Tree select parent values must be null, integers, or strings.');
            }

            $optionKey = $this->optionKey($value);

            if (isset($options[$optionKey])) {
                throw new InvalidArgumentException("Tree select option values must be unique. Duplicate value [{$value}] given.");
            }

            $description = filled($option['description'] ?? null) ? (string) $option['description'] : null;
            $label = (string) $option['label'];

            $options[$optionKey] = [
                'value' => $value,
                'parentValue' => $option['parent'],
                'label' => $label,
                'description' => $description,
                'selectedLabel' => (string) ($option['selectedLabel'] ?? ($description === null ? $label : "{$description} — {$label}")),
                'search' => Str::lower((string) ($option['search'] ?? implode(' ', array_filter([$label, $description, $value])))),
                'disabled' => (bool) ($option['disabled'] ?? false),
            ];
        }

        $childrenByParent = [];

        foreach ($options as $option) {
            $childrenByParent[$this->optionKey($option['parentValue'])][] = $option;
        }

        $rows = [];
        $visitedOptionKeys = [];

        foreach ($options as $option) {
            $parentValue = $option['parentValue'];

            if ($parentValue !== null && isset($options[$this->optionKey($parentValue)])) {
                continue;
            }

            $this->appendTreeRows($option, 0, [], $childrenByParent, $rows, $visitedOptionKeys);
        }

        foreach ($options as $option) {
            if (isset($visitedOptionKeys[$this->optionKey($option['value'])])) {
                continue;
            }

            $this->appendTreeRows($option, 0, [], $childrenByParent, $rows, $visitedOptionKeys);
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $option
     * @param  array<int, int|string>  $ancestorValues
     * @param  array<string, array<int, array<string, mixed>>>  $childrenByParent
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, bool>  $visitedOptionKeys
     */
    private function appendTreeRows(
        array $option,
        int $depth,
        array $ancestorValues,
        array $childrenByParent,
        array &$rows,
        array &$visitedOptionKeys,
    ): void {
        $optionKey = $this->optionKey($option['value']);

        if (isset($visitedOptionKeys[$optionKey])) {
            return;
        }

        $visitedOptionKeys[$optionKey] = true;
        $children = $childrenByParent[$optionKey] ?? [];
        $rows[] = [
            ...$option,
            'depth' => $depth,
            'hasChildren' => $children !== [],
            'ancestorValues' => $ancestorValues,
        ];

        foreach ($children as $child) {
            $this->appendTreeRows(
                option: $child,
                depth: $depth + 1,
                ancestorValues: [...$ancestorValues, $option['value']],
                childrenByParent: $childrenByParent,
                rows: $rows,
                visitedOptionKeys: $visitedOptionKeys,
            );
        }
    }

    private function optionKey(int|string|null $value): string
    {
        return $value === null ? '__tree_select_root__' : (string) $value;
    }
}
