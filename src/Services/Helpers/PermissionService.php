<?php

namespace Cronqvist\Api\Services\Helpers;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionService
{
    public static $tablePolicies = [
        'viewAny',
        'view',
        'viewOwn',
        'create',
        'update',
        'updateOwn',
        'delete',
        'deleteOwn',
        'restore',
        'restoreOwn',
        //'forceDelete',
        //'forceDeleteOwn',
    ];

    public static function createRoles(array $roles, $guard = 'api')
    {
        foreach($roles as $role) {
            Role::findOrCreate($role, $guard);
        }
    }

    public static function createPermissions(array $permissions, $guard = 'api')
    {
        foreach($permissions as $role) {
            Permission::findOrCreate($role, $guard);
        }
    }
}
