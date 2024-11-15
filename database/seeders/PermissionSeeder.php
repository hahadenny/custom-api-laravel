<?php

namespace Database\Seeders;

use App\Enums\PermissionEnum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        foreach (PermissionEnum::cases() as $permission) {
            Permission::findOrCreate($permission->value);
        }
    }
}
