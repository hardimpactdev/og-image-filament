<x-filament-panels::page>
    <div data-og-workflow style="display: grid; gap: 2rem">
        <x-filament::tabs label="OG image workflow">
            <x-filament::tabs.item
                :active="$activeTab === 'generate'"
                wire:click="$set('activeTab', 'generate')"
            >
                Generate
            </x-filament::tabs.item>

            <x-filament::tabs.item
                :active="$activeTab === 'configure'"
                wire:click="$set('activeTab', 'configure')"
            >
                Configure
            </x-filament::tabs.item>
        </x-filament::tabs>

        @if ($activeTab === 'generate')
            <div
                data-og-generator-layout
                x-data="ogImageFilamentGenerator"
                x-on:og-image-filament:generate.window="generate($event.detail.filename)"
                style="display: grid; gap: 1.5rem; min-width: 0"
            >
                <form wire:submit="generate" style="display: grid; gap: 1.5rem; min-width: 0">
                    {{ $this->form }}

                    <x-filament::button
                        type="submit"
                        x-bind:disabled="generating"
                        x-bind:aria-busy="generating"
                    >
                        <span x-show="! generating">Generate OG image</span>
                        <span x-cloak x-show="generating">Generating…</span>
                    </x-filament::button>

                    <p
                        x-cloak
                        x-show="error"
                        x-text="error"
                        class="text-sm text-danger-600"
                    ></p>
                </form>

                <aside class="min-w-0" style="min-width: 0; max-width: 100%">
                    <div class="rounded-xl border border-gray-200 bg-gray-100 p-4 dark:border-white/10 dark:bg-white/5" style="max-width: 100%; overflow: hidden">
                        <div
                            data-og-preview-frame
                            x-bind:style="`height: ${previewHeight}px`"
                            style="position: relative; width: 100%; overflow: hidden"
                        >
                            <div
                                data-og-preview
                                class="w-max shadow-xl"
                                x-bind:style="`width: 1200px; transform: scale(${previewScale}); transform-origin: top left`"
                            >
                            @include(
                                $this->previewTemplate(),
                                ['properties' => $this->previewProperties()]
                            )
                            </div>
                        </div>
                    </div>
                </aside>
            </div>

            <style>
                @media (min-width: 80rem) {
                    [data-og-generator-layout] {
                        grid-template-columns: minmax(20rem, 0.75fr) minmax(36rem, 1.25fr);
                    }
                }
            </style>
        @else
            <form wire:submit="saveSettings" style="display: grid; gap: 2rem">
                {{ $this->settingsForm }}

                <div class="flex justify-end">
                    <x-filament::button type="submit">
                        Save configuration
                    </x-filament::button>
                </div>
            </form>
        @endif
    </div>
</x-filament-panels::page>
