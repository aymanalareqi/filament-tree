<?php

namespace Alareqi\FilamentTree\Columns;

use Alareqi\FilamentTree\Concerns\InteractsWithTreeTable;
use Filament\Tables\Columns\TextColumn;

final class TreeColumn extends TextColumn
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->disabledClick();
    }

    public function toEmbeddedHtml(): string
    {
        $livewire = $this->getLivewire();

        if (! in_array(InteractsWithTreeTable::class, class_uses_recursive($livewire), true)) {
            return parent::toEmbeddedHtml();
        }

        return view('filament-tree::columns.tree-column', [
            'content' => parent::toEmbeddedHtml(),
            'depth' => $livewire->getTreeRecordDepth($this->getRecord()),
            'hasChildren' => $livewire->treeRecordHasChildren($this->getRecord()),
            'isExpanded' => $livewire->isTreeRecordExpanded($this->getRecord()),
            'recordKey' => $livewire->getTableRecordKey($this->getRecord()),
            'isReordering' => $livewire->isTreeReordering(),
            'expandLabel' => $livewire->getTreeConfiguration()->expandLabel,
            'collapseLabel' => $livewire->getTreeConfiguration()->collapseLabel,
            'expandedIcon' => $livewire->getTreeExpandedIcon($this->getRecord()),
            'collapsedIcon' => $livewire->getTreeCollapsedIcon($this->getRecord()),
            'leafIcon' => $livewire->getTreeLeafIcon($this->getRecord()),
            'expandIcon' => $livewire->getTreeExpandIcon($this->getRecord()),
            'collapseIcon' => $livewire->getTreeCollapseIcon($this->getRecord()),
        ])->render();
    }
}
