<x-filament-panels::page>
    @php($preview = $this->preview())

    <div data-og-workflow style="display: grid; gap: 2rem; min-width: 0">
        <style>
            @media (min-width: 80rem) {
                [data-og-generator-layout][data-has-preview] {
                    grid-template-columns: minmax(20rem, 0.75fr) minmax(36rem, 1.25fr);
                }
            }
        </style>

        <div
            data-og-generator-layout
            @if ($preview !== null) data-has-preview @endif
            style="display: grid; gap: 1.5rem; min-width: 0"
        >
            <form wire:submit="regenerate" style="display: grid; align-content: start; gap: 1.5rem; min-width: 0">
                {{ $this->form }}

                @if ($preview !== null)
                    <div>
                        <x-filament::button type="submit">
                            Regenerate OG image
                        </x-filament::button>
                    </div>
                @endif
            </form>

            @if ($preview !== null)
                <aside
                    class="min-w-0"
                    x-data="{
                        previewHeight: 630,
                        previewObserver: null,
                        previewScale: 1,
                        init() {
                            this.previewObserver = new ResizeObserver(() => this.syncPreview())
                            this.previewObserver.observe(this.$refs.previewFrame)
                            this.$nextTick(() => this.syncPreview())
                        },
                        destroy() {
                            this.previewObserver?.disconnect()
                        },
                        syncPreview() {
                            this.previewScale = Math.min(1, this.$refs.previewFrame.clientWidth / 1200)
                            this.previewHeight = 630 * this.previewScale
                        },
                    }"
                    style="min-width: 0; max-width: 100%"
                >
                    <div class="rounded-xl border border-gray-200 bg-gray-100 p-4 dark:border-white/10 dark:bg-white/5" style="max-width: 100%; overflow: hidden">
                        <div
                            data-og-preview-frame
                            x-ref="previewFrame"
                            x-bind:style="`height: ${previewHeight}px`"
                            style="position: relative; width: 100%; overflow: hidden"
                        >
                            <div
                                data-og-preview
                                class="w-max shadow-xl"
                                x-bind:style="`width: 1200px; transform: scale(${previewScale}); transform-origin: top left`"
                            >
                                @include($preview['template'], ['data' => $preview['data']])
                            </div>
                        </div>
                    </div>
                </aside>
            @endif
        </div>
    </div>
</x-filament-panels::page>
