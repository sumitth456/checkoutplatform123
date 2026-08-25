<div class="space-y-6">
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <div class="flex flex-wrap gap-3">
            <x-filament::button type="submit">
                Save form
            </x-filament::button>
            <x-filament::button type="button" color="gray" wire:click="saveAndInjectHtml">
                Save & inject into HTML
            </x-filament::button>
        </div>
    </form>

    <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <h3 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-200">Generated markup preview</h3>
        <pre class="overflow-x-auto rounded-lg bg-gray-950 p-4 text-xs text-gray-100">{{ $this->previewHtml() }}</pre>
    </div>
</div>
