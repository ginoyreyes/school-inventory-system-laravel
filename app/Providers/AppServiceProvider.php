<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $matrix = [
            'dashboard:read'      => ['Administrator', 'Inventory Staff', 'Viewer'],
            'inventory:read'      => ['Administrator', 'Inventory Staff', 'Viewer'],
            'inventory:create'    => ['Administrator', 'Inventory Staff'],
            'inventory:update'    => ['Administrator', 'Inventory Staff'],
            'inventory:archive'   => ['Administrator'],
            'transactions:read'   => ['Administrator', 'Inventory Staff', 'Viewer'],
            'transactions:create' => ['Administrator', 'Inventory Staff'],
            'transactions:adjust' => ['Administrator'],
            'borrows:read'        => ['Administrator', 'Inventory Staff', 'Viewer'],
            'borrows:create'      => ['Administrator', 'Inventory Staff'],
            'borrows:update'      => ['Administrator', 'Inventory Staff'],
            'categories:read'     => ['Administrator', 'Inventory Staff', 'Viewer'],
            'categories:manage'   => ['Administrator'],
            'places:read'         => ['Administrator', 'Inventory Staff', 'Viewer'],
            'places:manage'       => ['Administrator', 'Inventory Staff'],
            'reports:read'        => ['Administrator', 'Inventory Staff', 'Viewer'],
            'users:read'          => ['Administrator'],
            'users:manage'        => ['Administrator'],
            'logs:read'           => ['Administrator'],
            'settings:manage'     => ['Administrator'],
        ];

        foreach ($matrix as $permission => $allowedRoles) {
            Gate::define($permission, fn (User $user) => $user->is_active && in_array($user->role, $allowedRoles, true));
        }
    }
}