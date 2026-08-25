<x-filament-panels::page>
    @php
        /** @var \App\Filament\Admin\Resources\LandingPageTemplates\Pages\ListLandingPageTemplates $this */
        $canManage = \App\Support\Admin\AdminAuthorization::can(auth()->user(), 'landing_pages.templates.manage');
        $canCreatePages = \App\Support\Admin\AdminAuthorization::can(auth()->user(), 'landing_pages.create');
    @endphp

    <div class="lp-template-hub">
        <div class="lp-template-hub__toolbar">
            <div class="lp-template-hub__toolbar-left">
                <p class="lp-template-hub__count">{{ number_format($this->templateCount) }} templates</p>
                <div class="lp-template-hub__filters">
                    <input
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search templates..."
                        class="lp-template-hub__search"
                    />
                    <select wire:model.live="categoryFilter" class="lp-template-hub__select">
                        <option value="">All categories</option>
                        @foreach ($this->categories as $category)
                            <option value="{{ $category }}">{{ $category }}</option>
                        @endforeach
                    </select>
                    <select wire:model.live="statusFilter" class="lp-template-hub__select">
                        <option value="all">All statuses</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                    <select wire:model.live="sortBy" class="lp-template-hub__select">
                        <option value="updated_at">Sort by updated</option>
                        <option value="name">Sort by name</option>
                        <option value="created_at">Sort by created</option>
                    </select>
                </div>
            </div>
        </div>

        @if ($this->templates->isEmpty())
            <div class="lp-template-hub__empty">
                <h2>No templates yet</h2>
                <p>Save an existing landing page as a template, or create a new reusable design.</p>
                @if ($canManage)
                    <a href="{{ \App\Filament\Admin\Resources\LandingPageTemplates\LandingPageTemplateResource::getUrl('create') }}" class="lp-template-card__btn lp-template-card__btn--primary">
                        Create template
                    </a>
                @endif
            </div>
        @else
            <div class="lp-template-grid">
                @foreach ($this->templates as $template)
                    @php
                        $previewUrl = filled($template->preview_image_path)
                            ? \Illuminate\Support\Facades\Storage::disk('public')->url($template->preview_image_path)
                            : 'https://ui-avatars.com/api/?name='.urlencode($template->name).'&background=e2e8f0&color=475569&size=512';
                        $builderUrl = \App\Filament\Admin\Resources\LandingPageTemplates\LandingPageTemplateResource::getUrl('builder', ['record' => $template]);
                        $previewRoute = route('landing-page-templates.preview', $template);
                    @endphp

                    <article class="lp-template-card" wire:key="template-{{ $template->uuid }}">
                        <a href="{{ $previewRoute }}" target="_blank" class="lp-template-card__preview" aria-label="Preview {{ $template->name }}">
                            <img src="{{ $previewUrl }}" alt="" loading="lazy">
                            <div class="lp-template-card__badges">
                                @if (filled($template->category))
                                    <span class="lp-template-card__badge">{{ strtoupper($template->category) }}</span>
                                @else
                                    <span class="lp-template-card__badge">TEMPLATE</span>
                                @endif
                                <span @class(['lp-template-card__status', 'is-active' => $template->is_active, 'is-inactive' => ! $template->is_active])>
                                    <span class="lp-template-card__status-dot"></span>
                                    {{ $template->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                        </a>

                        <div class="lp-template-card__body">
                            <h3 class="lp-template-card__title">{{ $template->name }}</h3>
                            <p class="lp-template-card__description">
                                {{ filled($template->description) ? \Illuminate\Support\Str::limit($template->description, 90) : 'Reusable landing page design' }}
                            </p>
                            <p class="lp-template-card__meta">Updated {{ $template->updated_at?->format('M j, Y') ?? '—' }}</p>
                        </div>

                        <div class="lp-template-card__actions">
                            @if ($canManage)
                                <a href="{{ $builderUrl }}" class="lp-template-card__btn lp-template-card__btn--primary">Edit</a>
                            @endif

                            @if ($canCreatePages)
                                <button
                                    type="button"
                                    class="lp-template-card__btn"
                                    wire:click="mountAction('useTemplate', { templateUuid: '{{ $template->uuid }}' })"
                                >
                                    Use template
                                </button>
                            @endif

                            <details class="lp-template-card__menu">
                                <summary aria-label="More actions">⋯</summary>
                                <div class="lp-template-card__menu-panel">
                                    <a href="{{ $previewRoute }}" target="_blank">Preview</a>
                                    @if ($canManage)
                                        <button type="button" wire:click="duplicateTemplate('{{ $template->uuid }}')" wire:confirm="Duplicate this template?">Duplicate</button>
                                        <button type="button" wire:click="toggleTemplateActive('{{ $template->uuid }}')">
                                            {{ $template->is_active ? 'Deactivate' : 'Activate' }}
                                        </button>
                                        <a href="{{ \App\Filament\Admin\Resources\LandingPageTemplates\LandingPageTemplateResource::getUrl('edit', ['record' => $template]) }}">Settings</a>
                                        <button type="button" class="is-danger" wire:click="deleteTemplate('{{ $template->uuid }}')" wire:confirm="Delete this template permanently?">Delete</button>
                                    @endif
                                </div>
                            </details>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>

    <x-filament-actions::modals />

    @push('styles')
        <style>
            .lp-template-hub { display: grid; gap: 1.25rem; }
            .lp-template-hub__toolbar {
                display: flex; flex-wrap: wrap; justify-content: space-between; gap: 1rem;
                padding: 1rem 1.25rem; border: 1px solid rgba(148,163,184,.25); border-radius: 14px;
                background: rgba(255,255,255,.7);
            }
            .dark .lp-template-hub__toolbar { background: rgba(15,23,42,.5); }
            .lp-template-hub__count { margin: 0 0 .75rem; font-size: .875rem; color: #64748b; font-weight: 600; }
            .lp-template-hub__filters { display: flex; flex-wrap: wrap; gap: .75rem; }
            .lp-template-hub__search,
            .lp-template-hub__select {
                min-width: 180px; padding: .6rem .75rem; border-radius: 10px;
                border: 1px solid #cbd5e1; background: #fff; font-size: .875rem;
            }
            .dark .lp-template-hub__search,
            .dark .lp-template-hub__select { background: #0f172a; border-color: #334155; color: #e2e8f0; }
            .lp-template-grid {
                display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.25rem;
            }
            .lp-template-card {
                display: flex; flex-direction: column; overflow: hidden;
                border: 1px solid rgba(148,163,184,.25); border-radius: 16px;
                background: #fff; box-shadow: 0 10px 30px rgba(15,23,42,.06);
            }
            .dark .lp-template-card { background: #0f172a; }
            .lp-template-card__preview {
                position: relative; display: block; aspect-ratio: 16 / 10; overflow: hidden;
                background: linear-gradient(180deg, #f8fafc, #e2e8f0);
            }
            .lp-template-card__preview img { width: 100%; height: 100%; object-fit: cover; }
            .lp-template-card__badges {
                position: absolute; inset: 12px 12px auto 12px; display: flex; justify-content: space-between; gap: .5rem;
            }
            .lp-template-card__badge {
                padding: .25rem .55rem; border-radius: 999px; background: rgba(99,102,241,.92); color: #fff;
                font-size: .6875rem; font-weight: 700; letter-spacing: .04em;
            }
            .lp-template-card__status {
                display: inline-flex; align-items: center; gap: .35rem; padding: .25rem .55rem; border-radius: 999px;
                background: rgba(15,23,42,.72); color: #fff; font-size: .6875rem; font-weight: 600;
            }
            .lp-template-card__status-dot { width: 8px; height: 8px; border-radius: 999px; background: #94a3b8; }
            .lp-template-card__status.is-active .lp-template-card__status-dot { background: #22c55e; }
            .lp-template-card__body { padding: 1rem 1rem .75rem; display: grid; gap: .35rem; flex: 1; }
            .lp-template-card__title { margin: 0; font-size: 1rem; font-weight: 700; color: #0f172a; }
            .dark .lp-template-card__title { color: #f8fafc; }
            .lp-template-card__description,
            .lp-template-card__meta { margin: 0; font-size: .8125rem; color: #64748b; }
            .lp-template-card__actions {
                display: flex; flex-wrap: wrap; gap: .5rem; padding: 0 1rem 1rem; align-items: center;
            }
            .lp-template-card__btn {
                display: inline-flex; align-items: center; justify-content: center; padding: .55rem .85rem;
                border-radius: 10px; border: 1px solid #cbd5e1; background: #fff; color: #0f172a;
                font-size: .8125rem; font-weight: 600; text-decoration: none; cursor: pointer;
            }
            .lp-template-card__btn--primary {
                background: linear-gradient(135deg, #6366f1, #8b5cf6); border-color: transparent; color: #fff;
            }
            .lp-template-card__menu { position: relative; margin-left: auto; }
            .lp-template-card__menu summary {
                list-style: none; width: 36px; height: 36px; display: grid; place-items: center;
                border: 1px solid #cbd5e1; border-radius: 10px; cursor: pointer; font-size: 1.1rem;
            }
            .lp-template-card__menu summary::-webkit-details-marker { display: none; }
            .lp-template-card__menu-panel {
                position: absolute; right: 0; bottom: calc(100% + .35rem); min-width: 160px; padding: .35rem;
                border: 1px solid #e2e8f0; border-radius: 12px; background: #fff; box-shadow: 0 12px 30px rgba(15,23,42,.12);
                display: grid; z-index: 20;
            }
            .dark .lp-template-card__menu-panel { background: #111827; border-color: #334155; }
            .lp-template-card__menu-panel a,
            .lp-template-card__menu-panel button {
                padding: .55rem .75rem; border: 0; background: transparent; text-align: left;
                font-size: .8125rem; color: inherit; text-decoration: none; border-radius: 8px; cursor: pointer;
            }
            .lp-template-card__menu-panel a:hover,
            .lp-template-card__menu-panel button:hover { background: #f1f5f9; }
            .lp-template-card__menu-panel .is-danger { color: #dc2626; }
            .lp-template-hub__empty {
                padding: 3rem 1.5rem; text-align: center; border: 1px dashed #cbd5e1; border-radius: 16px;
            }
            @media (max-width: 640px) {
                .lp-template-hub__search, .lp-template-hub__select { min-width: 100%; }
                .lp-template-card__actions { flex-direction: column; align-items: stretch; }
                .lp-template-card__menu { margin-left: 0; }
            }
        </style>
    @endpush
</x-filament-panels::page>
