<?php

namespace Tests;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (Schema::hasTable(config('permission.table_names.permissions', 'permissions'))) {
            $this->seed(PermissionSeeder::class);
        }
    }

    public function actingAs(UserContract $user, $guard = null)
    {
        if ($user instanceof User && ! $user->roles()->exists()) {
            $user->assignRole('administrador');
        }

        return parent::actingAs($user, $guard);
    }
}
