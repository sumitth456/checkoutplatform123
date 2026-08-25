<div class="fi-section col-span-full mb-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
    <div class="grid gap-4 md:grid-cols-3">
        <div>
            <label class="text-sm font-medium text-gray-950 dark:text-white">From</label>
            <input
                type="date"
                wire:model.live="dateFrom"
                class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-white/10 dark:bg-white/5 dark:text-white"
            />
        </div>
        <div>
            <label class="text-sm font-medium text-gray-950 dark:text-white">To</label>
            <input
                type="date"
                wire:model.live="dateTo"
                class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-white/10 dark:bg-white/5 dark:text-white"
            />
        </div>
        <div class="flex items-end">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Views from traffic events; conversions from paid orders with landing page attribution.
            </p>
        </div>
    </div>
</div>
