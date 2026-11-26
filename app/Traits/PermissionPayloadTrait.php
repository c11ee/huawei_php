<?php

namespace App\Traits;

use App\Http\Resources\PermissionResource;
use App\Traits\TreeTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Guard;

trait PermissionPayloadTrait
{
    use TreeTrait;

    protected array $superRoles = ['超级管理员'];

    protected function permissionPayload(Model $user): array
    {
        $separated = $this->separatePermissions(
            PermissionResource::collection($this->resolvePermissions($user))->resolve()
        );

        return [
            'menu_permissions'   => $separated['menu_permissions'],
            'button_permissions' => $separated['button_permissions'],
        ];
    }

    protected function resolvePermissions(Model $user): Collection
    {
        if (! method_exists($user, 'hasAnyRole') || ! method_exists($user, 'getAllPermissions')) {
            return collect();
        }

        if ($user->hasAnyRole($this->superRoles)) {
            return Permission::query()
                ->where('guard_name', Guard::getDefaultName($user))
                ->get();
        }

        return $user->getAllPermissions();
    }

    protected function separatePermissions(array $permissions): array
    {
        $menuPermissions = [];
        $buttonPermissions = [];

        foreach ($permissions as $permission) {
            if ($permission['type'] === 1) {
                $menuPermissions[] = $permission;
            } else {
                $buttonPermissions[] = $permission;
            }
        }

        return [
            'menu_permissions'   => $this->buildTree($menuPermissions),
            'button_permissions' => $buttonPermissions,
        ];
    }
}
