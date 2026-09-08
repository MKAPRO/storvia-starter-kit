<?php

namespace Database\Seeders;

use App\Services\AccessControl\AccessControlProvisioner;
use Illuminate\Database\Seeder;

class AccessControlSeeder extends Seeder
{
    public function run(AccessControlProvisioner $provisioner): void
    {
        $provisioner->syncCatalog();
    }
}
