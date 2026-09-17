@php
    $options = $getTreeOptions();
    $isDisabled = $isDisabled();
    $isSearchable = $isSearchable();
    $statePath = $getStatePath();
@endphp

<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
    class="fi-fo-tree-select-wrp"
>
    <div
        x-data="{
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }},
            options: @js($options),
            expandedOptionValues: @js(collect($options)->where('depth', 0)->pluck('value')->values()),
            isOpen: false,
            search: '',

            init() {
                if (! this.selectedOption) {
                    return
                }

                this.expandedOptionValues = [
                    ...new Set([...this.expandedOptionValues, ...this.selectedOption.ancestorValues]),
                ]
            },

            get selectedOption() {
                return this.options.find((option) => String(option.value) === String(this.state)) ?? null
            },

            get visibleOptions() {
                const needle = this.search.trim().toLocaleLowerCase()

                if (needle !== '') {
                    const visibleOptionValues = new Set()

                    this.options
                        .filter((option) => option.search.includes(needle))
                        .forEach((option) => {
                            visibleOptionValues.add(option.value)
                            option.ancestorValues.forEach((ancestorValue) => visibleOptionValues.add(ancestorValue))
                        })

                    return this.options.filter((option) => visibleOptionValues.has(option.value))
                }

                return this.options.filter((option) =>
                    option.ancestorValues.every((ancestorValue) => this.isExpanded(ancestorValue)),
                )
            },

            open() {
                if (@js($isDisabled)) {
                    return
                }

                this.isOpen = true
                this.$nextTick(() => this.$refs.search?.focus())
            },

            close() {
                this.isOpen = false
                this.search = ''
            },

            choose(option) {
                if (option.disabled) {
                    return
                }

                this.state = option.value
                this.close()
            },

            choosePlaceholder() {
                this.state = null
                this.close()
            },

            isExpanded(optionValue) {
                return this.expandedOptionValues.some((value) => String(value) === String(optionValue))
            },

            toggle(optionValue) {
                if (this.isExpanded(optionValue)) {
                    this.expandedOptionValues = this.expandedOptionValues.filter(
                        (value) => String(value) !== String(optionValue),
                    )

                    return
                }

                this.expandedOptionValues.push(optionValue)
            },
        }"
        x-on:click.outside="close()"
        x-on:keydown.escape.stop="close()"
        class="relative"
        {{ $getExtraAttributeBag() }}
    >
        <x-filament::input.wrapper
            :disabled="$isDisabled"
            :valid="! $errors->has($statePath)"
            x-on:focus-input.stop="$el.querySelector('.fi-select-input-btn')?.focus()"
            class="fi-fo-tree-select"
        >
            <div class="fi-select-input">
                <div class="fi-select-input-ctn">
                    <button
                        id="{{ $getId() }}"
                        type="button"
                        role="combobox"
                        aria-haspopup="tree"
                        aria-controls="{{ $getId() }}-tree"
                        x-bind:aria-expanded="isOpen"
                        x-bind:class="{ 'fi-disabled': @js($isDisabled) }"
                        x-on:click="isOpen ? close() : open()"
                        @disabled($isDisabled)
                        class="fi-select-input-btn"
                    >
                        <span class="fi-select-input-value-ctn">
                            <span
                                class="fi-select-input-value-label"
                                x-bind:class="{ 'fi-select-input-placeholder': ! selectedOption }"
                                x-text="selectedOption?.selectedLabel ?? @js($getPlaceholder())"
                            ></span>
                        </span>
                    </button>
                </div>
            </div>
        </x-filament::input.wrapper>

        <div
            x-cloak
            x-show="isOpen"
            x-transition.opacity.duration.100ms
            class="absolute z-50 mt-2 w-full overflow-hidden rounded-xl bg-white shadow-xl ring-1 ring-gray-950/10 dark:bg-gray-900 dark:ring-white/20"
        >
            @if ($isSearchable)
                <div class="border-b border-gray-200 p-2 dark:border-white/10">
                    <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                        <x-filament::input
                            x-ref="search"
                            x-model.debounce.150ms="search"
                            type="search"
                            :placeholder="$getSearchPrompt()"
                            autocomplete="off"
                        />
                    </x-filament::input.wrapper>
                </div>
            @endif

            <div
                id="{{ $getId() }}-tree"
                role="tree"
                aria-label="{{ $getTreeLabel() }}"
                class="max-h-80 overflow-y-auto overscroll-contain p-1.5"
            >
                @if ($canSelectPlaceholder())
                    <button
                        type="button"
                        role="treeitem"
                        x-on:click="choosePlaceholder()"
                        x-bind:class="String(state ?? '') === '' ? 'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-400' : 'text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-white/5'"
                        class="flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-start text-sm"
                    >
                        <x-filament::icon icon="heroicon-o-home" class="size-5 shrink-0" />
                        <span class="truncate font-medium">{{ $getPlaceholder() }}</span>
                    </button>
                @endif

                <template x-for="option in visibleOptions" :key="`${typeof option.value}:${option.value}`">
                    <div
                        role="treeitem"
                        x-bind:aria-level="option.depth + 1"
                        x-bind:aria-expanded="option.hasChildren ? isExpanded(option.value) : null"
                        x-bind:aria-disabled="option.disabled"
                        x-bind:class="{
                            'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-400': String(option.value) === String(state),
                            'text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-white/5': ! option.disabled && String(option.value) !== String(state),
                            'cursor-not-allowed text-gray-400 opacity-70 dark:text-gray-500': option.disabled,
                        }"
                        class="flex items-center rounded-lg py-1 pe-2 text-sm"
                    >
                        <template x-for="level in option.depth" :key="level">
                            <span aria-hidden="true" class="h-8 w-4 shrink-0 border-s border-gray-200 dark:border-white/10"></span>
                        </template>

                        <button
                            x-show="option.hasChildren"
                            type="button"
                            x-on:click.stop="toggle(option.value)"
                            x-bind:aria-label="isExpanded(option.value) ? @js(__('Collapse')) : @js(__('Expand'))"
                            class="grid size-8 shrink-0 place-items-center rounded-md text-gray-400 hover:bg-gray-950/5 hover:text-gray-600 dark:text-gray-500 dark:hover:bg-white/10 dark:hover:text-gray-300"
                        >
                            <x-filament::icon
                                icon="heroicon-m-chevron-right"
                                x-bind:class="isExpanded(option.value) ? 'rotate-90 rtl:-rotate-90' : 'rtl:rotate-180'"
                                class="size-4 transition"
                            />
                        </button>

                        <span x-show="! option.hasChildren" aria-hidden="true" class="size-8 shrink-0"></span>

                        <button
                            type="button"
                            x-on:click="choose(option)"
                            x-bind:disabled="option.disabled"
                            class="flex min-w-0 flex-1 items-center gap-2 rounded-md px-1 py-1 text-start focus-visible:outline-2 focus-visible:outline-primary-600"
                        >
                            <x-filament::icon icon="heroicon-o-folder" class="size-5 shrink-0 text-primary-500" />

                            <span class="min-w-0 flex-1 truncate font-medium" x-text="option.label"></span>
                            <span
                                x-show="option.description"
                                class="shrink-0 font-mono text-xs text-gray-400 dark:text-gray-500"
                                x-text="option.description"
                            ></span>

                            <x-filament::icon
                                icon="heroicon-m-check"
                                x-show="String(option.value) === String(state)"
                                class="size-4 shrink-0 text-primary-600 dark:text-primary-400"
                            />
                        </button>
                    </div>
                </template>

                <div
                    x-show="visibleOptions.length === 0"
                    class="grid justify-items-center gap-2 px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400"
                >
                    <x-filament::icon icon="heroicon-o-magnifying-glass" class="size-7" />
                    <span x-text="search === '' ? @js((string) $getNoOptionsMessage()) : @js((string) $getNoSearchResultsMessage())"></span>
                </div>
            </div>
        </div>
    </div>
</x-dynamic-component>
