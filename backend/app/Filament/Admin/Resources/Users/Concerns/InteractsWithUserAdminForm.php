<?php

namespace App\Filament\Admin\Resources\Users\Concerns;

use App\Models\User;
use App\Modules\Admin\Services\AdminUserService;

trait InteractsWithUserAdminForm
{
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var User $record */
        $record = $this->record;

        $data['role_name'] = $record->roles->first()?->name;
        $data['campaign_ids'] = $record->campaigns()->pluck('campaigns.id')->all();
        $data['extra_permission_ids'] = app(AdminUserService::class)->directPermissionIds($record);
        $data['ip_whitelist_text'] = implode("\n", $record->ip_whitelist ?? []);

        return $data;
    }
}
