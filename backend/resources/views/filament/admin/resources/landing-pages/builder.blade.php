@php
    /** @var \App\Filament\Admin\Resources\LandingPages\Pages\BuildLandingPage $livewire */
    $tabs = [
        'html' => 'HTML',
        'css' => 'CSS',
        'js' => 'JavaScript',
        'header' => 'Header scripts',
        'footer' => 'Footer scripts',
    ];

    $builderConfig = [
        'document' => $livewire->builderDocument,
        'definitions' => $componentDefinitions,
        'defaultStyles' => $defaultStyles,
        'viewport' => $livewire->viewport,
        'selectedNodeId' => $livewire->selectedNodeId,
        'previewBaseSrc' => strtok($livewire->previewIframeSrc, '?'),
    ];
@endphp

@push('styles')
    @vite('resources/css/landing-page-visual-builder.css')
@endpush

@push('scripts')
    @vite('resources/js/landing-page-visual-builder.js')
@endpush

<div class="fi-lp-visual-builder">
    <div class="fi-lp-vb-toolbar">
        <div class="flex flex-wrap items-center gap-3">
            <div class="fi-lp-vb-mode-toggle">
                <button
                    type="button"
                    wire:click="setBuilderMode('visual')"
                    @class(['is-active' => $livewire->builderMode === 'visual'])
                >
                    Visual
                </button>
                <button
                    type="button"
                    wire:click="setBuilderMode('code')"
                    @class(['is-active' => $livewire->builderMode === 'code'])
                >
                    Code
                </button>
            </div>

            @if ($livewire->builderMode === 'visual')
                <div class="fi-lp-vb-viewports">
                    @foreach (['desktop' => 'Desktop', 'tablet' => 'Tablet', 'mobile' => 'Mobile'] as $vp => $label)
                        <button
                            type="button"
                            data-lp-viewport="{{ $vp }}"
                            wire:click="setViewport('{{ $vp }}')"
                            @class(['is-active' => $livewire->viewport === $vp])
                        >
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            @endif

            <span
                class="fi-lp-vb-status @if($livewire->saveState === 'auto') is-saved @endif"
                data-lp-status
            >
                @if ($livewire->saveState === 'auto')
                    Saved
                @else
                    Ready
                @endif
                @if ($livewire->lastSavedAt)
                    · {{ $livewire->lastSavedAt }}
                @endif
            </span>

            <span class="text-xs text-gray-500 dark:text-gray-400">
                @if (($builderContext ?? 'page') === 'template')
                    {{ ($livewire->record->is_active ?? false) ? 'Active template' : 'Inactive template' }}
                @else
                    Status: {{ $livewire->record->status->value ?? $livewire->record->status }}
                @endif
            </span>
        </div>

        <div class="flex flex-wrap gap-2">
            <x-filament::button type="button" color="gray" size="sm" wire:click="saveVersionNow">
                Save version
            </x-filament::button>
        </div>
    </div>

    @if ($livewire->builderMode === 'visual')
        <div data-lp-visual-builder wire:ignore.self>
            <script type="application/json" data-lp-config>@json($builderConfig)</script>

            <div class="fi-lp-vb-layout">
                <aside class="fi-lp-vb-panel">
                    <div class="fi-lp-vb-panel-head">Components</div>
                    <div class="fi-lp-vb-palette" data-lp-palette></div>
                </aside>

                <div class="fi-lp-vb-center">
                    <div class="fi-lp-vb-panel">
                        <div class="fi-lp-vb-panel-head">Canvas</div>
                        <div class="fi-lp-vb-canvas-wrap">
                            <div
                                class="fi-lp-vb-canvas is-{{ $livewire->viewport }}"
                                data-lp-canvas
                            >
                                <div class="fi-lp-vb-dropzone is-empty" data-lp-dropzone>
                                    Drag components here to start building
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="fi-lp-vb-panel fi-lp-vb-preview">
                        <div class="fi-lp-vb-panel-head">Live preview</div>
                        <iframe
                            data-lp-preview-iframe
                            title="Landing page preview"
                            sandbox="allow-scripts allow-same-origin allow-forms"
                            src="{{ $livewire->previewIframeSrc }}"
                            wire:key="lp-preview-{{ md5($livewire->previewIframeSrc) }}"
                        ></iframe>
                    </div>
                </div>

                <aside class="fi-lp-vb-panel">
                    <div class="fi-lp-vb-panel-head">Properties</div>
                    <div class="fi-lp-vb-props" data-lp-props></div>
                </aside>
            </div>
        </div>
    @else
        <div class="fi-lp-simple-builder flex flex-col gap-4">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Code editor — edit HTML, CSS, and JS directly. Changes auto-save after you stop typing.
            </p>

            <div class="grid min-h-[72vh] grid-cols-1 gap-4 xl:grid-cols-2">
                <div class="flex min-h-[72vh] flex-col overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="flex flex-wrap border-b border-gray-200 dark:border-white/10">
                        @foreach ($tabs as $id => $label)
                            <button
                                type="button"
                                wire:click="setActiveTab('{{ $id }}')"
                                @class([
                                    'px-4 py-2.5 text-sm font-medium transition',
                                    'border-b-2 border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400' => $livewire->activeTab === $id,
                                    'text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200' => $livewire->activeTab !== $id,
                                ])
                            >
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>

                    <div class="min-h-[60vh] flex-1 p-2">
                        @if ($livewire->activeTab === 'html')
                            <textarea wire:model.live.debounce.2000ms="contentHtml" class="fi-lp-code-area" spellcheck="false" aria-label="HTML"></textarea>
                        @elseif ($livewire->activeTab === 'css')
                            <textarea wire:model.live.debounce.2000ms="contentCss" class="fi-lp-code-area" spellcheck="false" aria-label="CSS"></textarea>
                        @elseif ($livewire->activeTab === 'js')
                            <textarea wire:model.live.debounce.2000ms="contentJs" class="fi-lp-code-area" spellcheck="false" aria-label="JavaScript"></textarea>
                        @elseif ($livewire->activeTab === 'header')
                            <textarea wire:model.live.debounce.2000ms="customHeaderScripts" class="fi-lp-code-area" spellcheck="false" aria-label="Header scripts"></textarea>
                        @else
                            <textarea wire:model.live.debounce.2000ms="customFooterScripts" class="fi-lp-code-area" spellcheck="false" aria-label="Footer scripts"></textarea>
                        @endif
                    </div>
                </div>

                <div class="flex min-h-[72vh] flex-col overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="border-b border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 dark:border-white/10 dark:text-gray-200">
                        Live preview
                    </div>
                    <iframe
                        class="min-h-[66vh] w-full flex-1 border-0 bg-white"
                        title="Landing page preview"
                        sandbox="allow-scripts allow-same-origin allow-forms"
                        src="{{ $livewire->previewIframeSrc }}"
                        wire:key="lp-preview-code-{{ md5($livewire->previewIframeSrc) }}"
                    ></iframe>
                </div>
            </div>
        </div>
    @endif
</div>

<script>
    function bootVisualBuilder() {
        document.querySelectorAll('[data-lp-visual-builder]:not([data-lp-initialized])').forEach((root) => {
            const configEl = root.querySelector('[data-lp-config]');
            if (!configEl || typeof Livewire === 'undefined') {
                return;
            }
            let config;
            try {
                config = JSON.parse(configEl.textContent || '{}');
            } catch {
                return;
            }
            const wireId = root.closest('[wire\\:id]')?.getAttribute('wire:id');
            const wire = wireId ? Livewire.find(wireId) : null;
            if (!wire || typeof window.initLandingPageVisualBuilder !== 'function') {
                return;
            }
            config.livewire = wire;
            window.initLandingPageVisualBuilder(root, config);
        });
    }

    document.addEventListener('DOMContentLoaded', bootVisualBuilder);
    document.addEventListener('livewire:navigated', bootVisualBuilder);

    document.addEventListener('livewire:init', () => {
        Livewire.on('lp-preview-refresh', () => {
            window.dispatchEvent(new Event('lp-preview-refresh'));
        });
    });

    document.addEventListener('livewire:initialized', () => {
        Livewire.hook('commit', ({ succeed }) => {
            succeed(() => {
                window.setTimeout(bootVisualBuilder, 50);
            });
        });
    });
</script>
