<?php

namespace App\Modules\Admin\Services;

use App\Models\Campaign;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminUserService
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function normalizeUserPayload(array $data): array
    {
        $first = trim((string) ($data['first_name'] ?? ''));
        $last = trim((string) ($data['last_name'] ?? ''));
        $composed = trim("{$first} {$last}");

        if ($composed !== '') {
            $data['name'] = $composed;
        } elseif (blank($data['name'] ?? null)) {
            $data['name'] = (string) ($data['email'] ?? 'User');
        }

        if (isset($data['username'])) {
            $data['username'] = Str::lower(trim((string) $data['username']));
        }

        if (isset($data['ip_whitelist_text'])) {
            $data['ip_whitelist'] = $this->parseIpWhitelist((string) $data['ip_whitelist_text']);
            unset($data['ip_whitelist_text']);
        }

        unset($data['password_confirmation'], $data['role_name'], $data['campaign_ids'], $data['extra_permission_ids']);

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createUser(User $actor, array $data): User
    {
        return DB::transaction(function () use ($actor, $data): User {
            $payload = $this->normalizeUserPayload($data);
            $roleName = (string) ($data['role_name'] ?? '');
            $campaignIds = array_map('intval', $data['campaign_ids'] ?? []);
            $extraPermissionIds = array_map('intval', $data['extra_permission_ids'] ?? []);

            $payload['created_by'] = $actor->id;
            $payload['updated_by'] = $actor->id;

            /** @var User $user */
            $user = User::query()->create($payload);

            $this->syncRole($user, $roleName);
            $this->syncCampaignAccess($user, $campaignIds);
            $this->syncExtraPermissions($user, $extraPermissionIds);

            return $user->fresh(['roles', 'permissions', 'campaigns', 'createdBy', 'updatedBy']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateUser(User $actor, User $user, array $data): User
    {
        $this->assertCanDeactivate($actor, $user, (string) ($data['status'] ?? $user->status));

        return DB::transaction(function () use ($actor, $user, $data): User {
            $roleName = (string) ($data['role_name'] ?? $user->roles->first()?->name ?? '');
            $campaignIds = array_key_exists('campaign_ids', $data)
                ? array_map('intval', $data['campaign_ids'] ?? [])
                : null;
            $extraPermissionIds = array_key_exists('extra_permission_ids', $data)
                ? array_map('intval', $data['extra_permission_ids'] ?? [])
                : null;

            $payload = $this->normalizeUserPayload($data);
            $payload['updated_by'] = $actor->id;

            $user->update($payload);

            if ($roleName !== '') {
                $this->syncRole($user, $roleName);
            }

            if ($campaignIds !== null) {
                $this->syncCampaignAccess($user, $campaignIds);
            }

            if ($extraPermissionIds !== null) {
                $this->syncExtraPermissions($user, $extraPermissionIds);
            }

            if ($user->force_logout_other_devices) {
                $this->invalidateOtherSessions($user);
            }

            return $user->fresh(['roles', 'permissions', 'campaigns', 'createdBy', 'updatedBy']);
        });
    }

    public function resetPassword(User $user, string $password): void
    {
        $user->update([
            'password' => Hash::make($password),
            'require_password_change' => false,
        ]);

        $this->invalidateOtherSessions($user);
    }

    public function duplicateUser(User $actor, User $source): User
    {
        $suffix = Str::lower(Str::random(4));

        $password = Str::password(16);

        return $this->createUser($actor, [
            'first_name' => $source->first_name,
            'last_name' => $source->last_name.' (Copy)',
            'username' => ($source->username ?: Str::before($source->email, '@')).'-'.$suffix,
            'email' => Str::before($source->email, '@')."+{$suffix}@".Str::after($source->email, '@'),
            'phone' => $source->phone,
            'password' => $password,
            'password_confirmation' => $password,
            'status' => 'inactive',
            'timezone' => $source->timezone,
            'locale' => $source->locale,
            'ip_whitelist_text' => implode("\n", $source->ip_whitelist ?? []),
            'two_factor_enabled' => $source->two_factor_enabled,
            'require_password_change' => true,
            'force_logout_other_devices' => false,
            'role_name' => $source->roles->first()?->name,
            'campaign_ids' => $source->campaigns()->pluck('campaigns.id')->all(),
            'extra_permission_ids' => $source->permissions->pluck('id')->all(),
        ]);
    }

    public function recordLogin(User $user, ?string $ip): void
    {
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $ip,
        ]);

        if ($user->force_logout_other_devices) {
            $this->invalidateOtherSessions($user);
        }
    }

    public function assertCanDeactivate(User $actor, User $target, string $status): void
    {
        if ($actor->id === $target->id && $status !== 'active') {
            throw ValidationException::withMessages([
                'status' => 'You cannot deactivate your own account.',
            ]);
        }
    }

    /**
     * @return list<string>
     */
    public function parseIpWhitelist(string $textarea): array
    {
        if (trim($textarea) === '') {
            return [];
        }

        $lines = preg_split('/\R/', $textarea) ?: [];

        return collect($lines)
            ->map(fn (string $line): string => trim($line))
            ->filter(fn (string $line): bool => $line !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    public function directPermissionIds(User $user): array
    {
        return $user->permissions()->pluck('id')->all();
    }

    private function syncRole(User $user, string $roleName): void
    {
        if ($roleName === '') {
            return;
        }

        $user->syncRoles([$roleName]);
    }

    /**
     * @param  list<int>  $campaignIds
     */
    private function syncCampaignAccess(User $user, array $campaignIds): void
    {
        if ($campaignIds === []) {
            $user->campaigns()->sync([]);

            return;
        }

        $validIds = Campaign::query()->whereIn('id', $campaignIds)->pluck('id')->all();
        $user->campaigns()->sync($validIds);
    }

    /**
     * @param  list<int>  $permissionIds
     */
    private function syncExtraPermissions(User $user, array $permissionIds): void
    {
        $names = Permission::query()
            ->whereIn('id', $permissionIds)
            ->pluck('name')
            ->all();

        $user->syncPermissions($names);
    }

    private function invalidateOtherSessions(User $user): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('sessions')) {
            return;
        }

        $currentSessionId = session()->getId();

        DB::table('sessions')
            ->where('user_id', $user->id)
            ->when(filled($currentSessionId), fn ($query) => $query->where('id', '!=', $currentSessionId))
            ->delete();
    }
}
