<?php

namespace Alareqi\FilamentTree;

final class TreeConfiguration
{
    public function __construct(
        public readonly string $parentColumn,
        public readonly string $treeColumn,
        public readonly mixed $rootValue = null,
        public readonly bool $defaultExpanded = true,
        public readonly string $expandLabel = 'Expand',
        public readonly string $collapseLabel = 'Collapse',
        public readonly ?string $orderColumn = null,
        public readonly string $orderDirection = 'asc',
        public readonly bool $reorderable = false,
    ) {}
}
