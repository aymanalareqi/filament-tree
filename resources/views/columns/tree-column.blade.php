<div
    class="fi-tree-node flex min-w-0 items-center gap-2.5"
    style="gap: 0.625rem; padding-inline-start: {{ $depth * 1.25 }}rem"
    data-tree-record-key="{{ $recordKey }}"
    data-tree-depth="{{ $depth }}"
    @if ($isReordering)
        x-init="
            const row = $el.closest('tr, .fi-ta-record')
            const container = row?.parentElement

            if (! row || ! container) return

            row.dataset.treeSortableItem = String(@js($recordKey))

            const bindTreeRows = () => {
                container.querySelectorAll('.fi-tree-node[data-tree-record-key]').forEach((node) => {
                    const treeRow = node.closest('tr, .fi-ta-record')
                    const key = String(node.dataset.treeRecordKey)

                    if (treeRow && treeRow.dataset.treeSortableItem !== key) {
                        treeRow.dataset.treeSortableItem = key
                    }
                })
            }

            bindTreeRows()

            if (! container.treeSortableObserver) {
                container.treeSortableObserver = new MutationObserver(bindTreeRows)
                container.treeSortableObserver.observe(container, {
                    attributeFilter: ['data-tree-sortable-item'],
                    attributes: true,
                    childList: true,
                    subtree: true,
                })
            }

            const initializeTreeSortable = () => {
                if (! container.isConnected) return
                if (container.treeSortable) return

                if (! window.Sortable) {
                    window.requestAnimationFrame(initializeTreeSortable)

                    return
                }

                const clearTarget = () => {
                    const target = container.treeDrop?.target
                    target?.style.removeProperty('box-shadow')
                    target?.style.removeProperty('outline')
                    target?.style.removeProperty('outline-offset')
                }

                const trackTarget = (event) => {
                    const drag = container.treeDrop
                    if (! drag) return
                    clearTarget()
                    delete drag.target
                    delete drag.targetKey
                    delete drag.position
                    const point = event.touches?.[0] ?? event.changedTouches?.[0] ?? event
                    const target = Array.from(container.children).find((candidate) => {
                        if (! candidate.dataset.treeSortableItem) return false
                        const bounds = candidate.getBoundingClientRect()
                        return point.clientX >= bounds.left && point.clientX <= bounds.right
                            && point.clientY >= bounds.top && point.clientY <= bounds.bottom
                    })
                    if (! target || drag.invalidKeys.has(String(target.dataset.treeSortableItem))) return
                    const bounds = target.getBoundingClientRect()
                    const ratio = (point.clientY - bounds.top) / bounds.height
                    const position = ratio < 0.25 ? 'before' : (ratio > 0.75 ? 'after' : 'inside')
                    if (position === 'inside') {
                        target.style.outline = '2px solid var(--primary-500)'
                        target.style.outlineOffset = '-2px'
                    } else {
                        target.style.boxShadow = position === 'before'
                            ? 'inset 0 3px 0 var(--primary-500)'
                            : 'inset 0 -3px 0 var(--primary-500)'
                    }
                    Object.assign(drag, { target, targetKey: String(target.dataset.treeSortableItem), position })
                }
                const pointerEvents = ['dragover', 'pointermove', 'touchmove']

                container.treeSortable = window.Sortable.create(container, {
                    animation: 180,
                    draggable: '[data-tree-sortable-item]',
                    handle: '.fi-tree-drag-handle',
                    dataIdAttr: 'data-tree-sortable-item',
                    ghostClass: 'fi-sortable-ghost',
                    onStart: (event) => {
                        bindTreeRows()
                        const rows = Array.from(container.children)
                        const index = rows.indexOf(event.item)
                        const depth = Number(event.item.querySelector('[data-tree-depth]').dataset.treeDepth)
                        const invalidKeys = new Set([String(event.item.dataset.treeSortableItem)])
                        for (const child of rows.slice(index + 1)) {
                            const node = child.querySelector('[data-tree-depth]')
                            if (! node || Number(node.dataset.treeDepth) <= depth) break
                            invalidKeys.add(String(child.dataset.treeSortableItem))
                        }
                        container.treeDrop = {
                            draggedKey: String(event.item.dataset.treeSortableItem),
                            invalidKeys,
                        }
                        pointerEvents.forEach((name) => document.addEventListener(name, trackTarget, true))
                    },
                    onMove: (event, pointerEvent) => {
                        trackTarget(pointerEvent)
                        return false
                    },
                    onEnd: () => {
                        pointerEvents.forEach((name) => document.removeEventListener(name, trackTarget, true))
                        const drop = container.treeDrop
                        clearTarget()
                        delete container.treeDrop

                        if (drop?.targetKey && drop?.position) {
                            $wire.stageTreeRecordDrop(drop.draggedKey, drop.targetKey, drop.position)
                        } else {
                            $wire.$refresh()
                        }
                    },
                })
            }

            $nextTick(initializeTreeSortable)
        "
    @else
        x-init="
            const row = $el.closest('tr, .fi-ta-record')
            const container = row?.parentElement
            if (container?.treeSortable) {
                container.treeSortable.destroy()
                delete container.treeSortable
                container.treeSortableObserver?.disconnect()
                delete container.treeSortableObserver
                container.querySelectorAll('[data-tree-sortable-item]').forEach((item) => delete item.dataset.treeSortableItem)
            }
        "
    @endif
>
    <div class="flex shrink-0 items-center gap-1.5" style="gap: 0.375rem">
        @if ($isReordering)
            <button
                type="button"
                class="fi-tree-drag-handle inline-flex size-8 shrink-0 cursor-grab items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-500 shadow-xs transition hover:text-gray-700 active:cursor-grabbing dark:border-white/10 dark:bg-white/5 dark:text-gray-400"
                style="box-sizing: border-box; height: 2rem; width: 2rem; margin: 0; padding: 0; border: 1px solid var(--gray-300); border-radius: 0.5rem"
                aria-label="{{ __('filament-tables::table.actions.enable_reordering.label') }}"
                x-on:click.stop.prevent
            >
                <x-filament::icon icon="heroicon-m-bars-3" class="size-4" />
            </button>
        @endif

        @if ($hasChildren)
            <button
                type="button"
                class="inline-flex size-8 shrink-0 items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-500 shadow-xs transition hover:text-gray-700 dark:border-white/10 dark:bg-white/5 dark:text-gray-400"
                style="box-sizing: border-box; height: 2rem; width: 2rem; margin: 0; padding: 0; border: 1px solid var(--gray-300); border-radius: 0.5rem"
                aria-label="{{ __($isExpanded ? $collapseLabel : $expandLabel) }}"
                aria-expanded="{{ $isExpanded ? 'true' : 'false' }}"
                x-data="{ loading: false }"
                x-bind:disabled="loading"
                x-on:click.stop.prevent="
                    if (loading) return
                    loading = true
                    $wire.toggleTreeRecord(@js($recordKey)).finally(() => loading = false)
                "
            >
                <x-filament::icon
                    :icon="$isExpanded ? $collapseIcon : $expandIcon"
                    class="size-4"
                    x-show="! loading"
                />
                <x-filament::loading-indicator
                    class="size-4"
                    x-cloak
                    x-show="loading"
                />
            </button>
        @else
            <span aria-hidden="true" class="size-8 shrink-0" style="height: 2rem; width: 2rem"></span>
        @endif
    </div>

    <x-filament::icon
        :icon="$hasChildren ? ($isExpanded ? $expandedIcon : $collapsedIcon) : $leafIcon"
        @class([
            'size-5 shrink-0',
            'text-primary-600 dark:text-primary-400' => $hasChildren,
            'text-gray-400 dark:text-gray-500' => ! $hasChildren,
        ])
    />

    <div class="min-w-0 flex-1">{!! $content !!}</div>
</div>
