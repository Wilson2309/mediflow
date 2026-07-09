<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$perms = App\Support\RolePermissions::all();
$dbPerms = Spatie\Permission\Models\Permission::pluck('name')->toArray();
$missing = array_diff($perms, $dbPerms);
print_r($missing);
