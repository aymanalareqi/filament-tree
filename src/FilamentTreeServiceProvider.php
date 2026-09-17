<?php

namespace Alareqi\FilamentTree;

use Alareqi\FilamentTree\Columns\TreeColumn;
use Filament\Actions\Action;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use LogicException;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class FilamentTreeServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-tree';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(self::$name)
            ->hasViews()
            ->hasTranslations();
    }

    public function packageBooted(): void
    {
        FilamentAsset::register([
            Css::make('filament-tree', __DIR__.'/../resources/dist/filament-tree.css'),
        ], 'alareqi/filament-tree');

        Table::macro('tree', function (
            string $parentColumn = 'parent_id',
            string $treeColumn = 'name',
            mixed $rootValue = null,
            bool $defaultExpanded = true,
            string $expandLabel = 'Expand',
            string $collapseLabel = 'Collapse',
        ): Table {
            /** @var Table $this */
            if (! $this->getColumn($treeColumn) instanceof TreeColumn) {
                throw new LogicException("Tree column [{$treeColumn}] must be an instance of ".TreeColumn::class.'.');
            }

            $orderColumn = $this->getReorderColumn();
            $orderDirection = $this->getReorderDirection();
            $isReorderable = $this->isReorderable();
            $livewire = $this->getLivewire();

            TreeTable::configure($this, new TreeConfiguration(
                parentColumn: $parentColumn,
                treeColumn: $treeColumn,
                rootValue: $rootValue,
                defaultExpanded: $defaultExpanded,
                expandLabel: $expandLabel,
                collapseLabel: $collapseLabel,
                orderColumn: $orderColumn,
                orderDirection: $orderDirection,
                reorderable: $isReorderable,
            ));

            return $this
                ->paginated(false)
                ->pushToolbarActions([
                    $livewire->configureTreeExpandAllAction(Action::make('expandAllTreeRecords')
                        ->label(__('filament-tree::tree.expand_all'))
                        ->icon(Heroicon::ArrowsPointingOut)
                        ->iconButton()
                        ->color('gray')
                        ->action('expandAllTreeRecords')
                        ->extraAttributes(['style' => 'margin: -0.25rem;'])),
                    $livewire->configureTreeCollapseAllAction(Action::make('collapseAllTreeRecords')
                        ->label(__('filament-tree::tree.collapse_all'))
                        ->icon(Heroicon::ArrowsPointingIn)
                        ->iconButton()
                        ->color('gray')
                        ->action('collapseAllTreeRecords')
                        ->extraAttributes(['style' => 'margin: -0.25rem;'])),
                ])
                ->reorderRecordsTriggerAction(fn (Action $action): Action => $livewire->configureTreeReorderAction(
                    $action
                        ->label(fn ($livewire): string => $livewire->isTreeReordering()
                            ? __('filament-tables::table.actions.disable_reordering.label')
                            : __('filament-tables::table.actions.enable_reordering.label'))
                        ->icon(fn ($livewire): Heroicon => $livewire->isTreeReordering()
                            ? Heroicon::Check
                            : Heroicon::ArrowsUpDown)
                        ->action('toggleTreeReordering')
                        ->disabled(fn ($livewire): bool => ! $livewire->isTreeReordering() && ! $livewire->canReorderTree())
                        ->extraAttributes(['style' => 'margin: -0.25rem;'], merge: true)
                )
                );
        });
    }
}
