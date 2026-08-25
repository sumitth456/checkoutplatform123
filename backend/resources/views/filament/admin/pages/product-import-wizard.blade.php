<div class="space-y-6" @if ($step === 5 && $this->batch && in_array($this->batch->status->value, ['queued', 'running'], true)) wire:poll.5s @endif>

    <div class="flex flex-wrap gap-2">

        @foreach ([1 => 'Upload', 2 => 'Preview', 3 => 'Validate', 4 => 'Map columns', 5 => 'Import'] as $number => $label)

            <button

                type="button"

                wire:click="goToStep({{ $number }})"

                @class([

                    'rounded-lg px-3 py-1.5 text-sm font-medium ring-1 ring-inset',

                    $step === $number

                        ? 'bg-primary-600 text-white ring-primary-600'

                        : 'bg-white text-gray-700 ring-gray-300 dark:bg-gray-900 dark:text-gray-200 dark:ring-white/10',

                ])

            >

                {{ $number }}. {{ $label }}

            </button>

        @endforeach

    </div>



    @if ($step === 1)

        <x-filament::section heading="Step 1 — Upload file">

            <form wire:submit="uploadFile" class="space-y-4">

                {{ $this->form }}

                <x-filament::button type="submit">Upload and continue</x-filament::button>

            </form>

        </x-filament::section>

    @endif



    @if ($step >= 2 && $this->batch)

        <x-filament::section heading="Step 2 — Preview data">

            <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">

                File: <strong>{{ $this->batch->original_filename }}</strong>

                · Status: <strong>{{ $this->batch->status->value }}</strong>

            </p>

            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">

                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">

                    <thead class="bg-gray-50 dark:bg-white/5">

                        <tr>

                            <th class="px-3 py-2 text-left">Row</th>

                            @foreach ($this->batch->headers ?? [] as $header)

                                <th class="px-3 py-2 text-left">{{ $header }}</th>

                            @endforeach

                        </tr>

                    </thead>

                    <tbody>

                        @foreach ($this->batch->preview_rows ?? [] as $preview)

                            <tr class="border-t border-gray-100 dark:border-white/5">

                                <td class="px-3 py-2">{{ $preview['row_number'] ?? '—' }}</td>

                                @foreach ($this->batch->headers ?? [] as $header)

                                    <td class="px-3 py-2">{{ $preview['raw'][$header] ?? '—' }}</td>

                                @endforeach

                            </tr>

                        @endforeach

                    </tbody>

                </table>

            </div>

            @if ($step === 2)

                <div class="mt-4 flex gap-2">

                    <x-filament::button wire:click="goToStep(4)">Continue to column mapping</x-filament::button>

                    <x-filament::button color="gray" wire:click="goToStep(3)">Skip to validation</x-filament::button>

                </div>

            @endif

        </x-filament::section>

    @endif



    @if ($step === 4 && $this->batch)

        <x-filament::section heading="Step 4 — Map columns">

            <div class="grid gap-3 md:grid-cols-2">

                @foreach ($this->systemFields() as $fieldKey => $fieldLabel)

                    <label class="flex flex-col gap-1 text-sm">

                        <span class="font-medium">{{ $fieldLabel }}</span>

                        <select wire:model.live="columnMapping.{{ $fieldKey }}" class="fi-input block w-full rounded-lg border-gray-300 dark:border-white/10 dark:bg-gray-900">

                            <option value="">— Not mapped —</option>

                            @foreach ($this->batch->headers ?? [] as $index => $header)

                                <option value="{{ $index }}">{{ $header }}</option>

                            @endforeach

                        </select>

                    </label>

                @endforeach

            </div>

            <div class="mt-4 flex flex-wrap gap-2">

                <x-filament::button wire:click="saveColumnMapping">Save mapping & validate</x-filament::button>

            </div>

        </x-filament::section>

    @endif



    @if ($step === 3 && $this->batch)

        <x-filament::section heading="Step 3 — Validation results">

            @php

                $validation = $this->batch->stats['validation'] ?? [];

            @endphp

            <p class="mb-4 text-sm">

                Valid products: <strong>{{ $validation['valid_product_count'] ?? 0 }}</strong>

                · Errors: <strong>{{ $validation['error_count'] ?? $this->batch->errors->count() }}</strong>

            </p>

            <div class="mb-4 flex gap-2">

                <x-filament::button wire:click="runValidation">Run validation</x-filament::button>

                <x-filament::button color="gray" wire:click="goToStep(4)">Adjust column mapping</x-filament::button>

            </div>

            @if ($this->batch->errors->isNotEmpty())

                <div class="mb-4 overflow-x-auto rounded-lg border border-danger-200">

                    <table class="min-w-full text-sm">

                        <thead class="bg-danger-50">

                            <tr>

                                <th class="px-3 py-2 text-left">Row</th>

                                <th class="px-3 py-2 text-left">SKU</th>

                                <th class="px-3 py-2 text-left">Field</th>

                                <th class="px-3 py-2 text-left">Message</th>

                            </tr>

                        </thead>

                        <tbody>

                            @foreach ($this->batch->errors->take(100) as $error)

                                <tr class="border-t">

                                    <td class="px-3 py-2">{{ $error->row_number }}</td>

                                    <td class="px-3 py-2">{{ $error->sku ?? '—' }}</td>

                                    <td class="px-3 py-2">{{ $error->field ?? '—' }}</td>

                                    <td class="px-3 py-2">{{ $error->message }}</td>

                                </tr>

                            @endforeach

                        </tbody>

                    </table>

                </div>

                <x-filament::button color="danger" wire:click="downloadErrorReport">Download error report (CSV)</x-filament::button>

            @endif

            <div class="mt-4">

                <x-filament::button wire:click="goToStep(5)" @disabled($this->batch->status->value !== 'validated')>

                    Continue to import

                </x-filament::button>

            </div>

        </x-filament::section>

    @endif



    @if ($step === 5 && $this->batch)

        <x-filament::section heading="Step 5 — Import">

            <div class="grid gap-3 md:grid-cols-2">

                <label class="flex flex-col gap-1 text-sm">

                    <span class="font-medium">Campaign (funnel) assignment</span>

                    <select wire:model.live="targetFunnelId" class="fi-input block w-full rounded-lg border-gray-300 dark:border-white/10 dark:bg-gray-900">

                        <option value="">— None —</option>

                        @foreach (\App\Models\Funnel::query()->orderBy('name')->pluck('name', 'id') as $id => $name)

                            <option value="{{ $id }}">{{ $name }}</option>

                        @endforeach

                    </select>

                </label>

            </div>

            <p class="mt-4 text-sm text-gray-600 dark:text-gray-400">

                Valid rows will import via the queue. Status: <strong>{{ $this->batch->status->value }}</strong>

            </p>

            @if (in_array($this->batch->status->value, ['queued', 'running'], true))

                <div class="mt-4">

                    <p class="mb-2 text-sm font-medium">Import in progress…</p>

                    <div class="h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-white/10">

                        <div class="h-full animate-pulse bg-primary-600" style="width: 66%"></div>

                    </div>

                </div>

            @endif

            @if ($this->batch->status->value === 'completed')

                <p class="mt-2 text-success-600">

                    Imported {{ $this->batch->stats['imported_count'] ?? 0 }} products

                    @if (filled($this->batch->stats['duration_ms'] ?? null))

                        in {{ $this->batch->stats['duration_ms'] }} ms.

                    @endif

                </p>

            @endif

            @if ($this->batch->status->value === 'failed')

                <p class="mt-2 text-danger-600">{{ $this->batch->failure_message ?? 'Import failed.' }}</p>

            @endif

            <div class="mt-4 flex gap-2">

                @if (! in_array($this->batch->status->value, ['queued', 'running', 'completed'], true))

                    <x-filament::button wire:click="startImport">Start import</x-filament::button>

                @endif

            </div>

        </x-filament::section>

    @endif

</div>


