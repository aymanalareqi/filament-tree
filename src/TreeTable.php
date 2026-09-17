<?php

namespace Alareqi\FilamentTree;

use Filament\Tables\Table;
use LogicException;
use WeakMap;

final class TreeTable
{
    /** @var WeakMap<Table, TreeConfiguration>|null */
    private static ?WeakMap $configurations = null;

    public static function configure(Table $table, TreeConfiguration $configuration): void
    {
        self::$configurations ??= new WeakMap;
        self::$configurations[$table] = $configuration;
    }

    public static function configuration(Table $table): TreeConfiguration
    {
        $configuration = self::$configurations?->offsetGet($table);

        if (! $configuration instanceof TreeConfiguration) {
            throw new LogicException('The table must be configured with tree() before using InteractsWithTreeTable.');
        }

        return $configuration;
    }

    public static function isConfigured(Table $table): bool
    {
        return self::$configurations?->offsetExists($table) ?? false;
    }
}
