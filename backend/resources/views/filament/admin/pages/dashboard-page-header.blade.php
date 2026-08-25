<div class="cp-dashboard-page-header col-span-full">
    <div class="fi-page-header-heading cp-dashboard-page-header__title">
        Operations Dashboard
    </div>

    @include('filament.admin.pages.dashboard-filters', [
        'options' => $options ?? [],
        'summary' => $summary ?? [],
    ])
</div>
