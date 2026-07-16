<x-filament-panels::page>
    <div class="space-y-6">
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
                class="grid gap-6 xl:grid-cols-[minmax(20rem,0.75fr)_minmax(36rem,1.25fr)]"
                x-data="ogImageFilamentGenerator"
                x-on:og-image-filament:generate.window="generate($event.detail.filename)"
            >
                <form wire:submit="generate" class="space-y-6">
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

                <aside class="min-w-0">
                    <div class="overflow-auto rounded-xl border border-gray-200 bg-gray-100 p-4 dark:border-white/10 dark:bg-white/5">
                        <div data-og-preview class="w-max shadow-xl">
                            @include(
                                $this->previewTemplate(),
                                ['properties' => $this->previewProperties()]
                            )
                        </div>
                    </div>
                </aside>
            </div>
        @else
            <form wire:submit="saveSettings" class="space-y-6">
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
