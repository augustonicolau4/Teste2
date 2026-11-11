<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Povoa a base de dados do autogest_nicola.
     *
     * Ordem: perfis/permissões -> dados-base (Core) -> dados de demonstração.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            CoreSeeder::class,
            DemoSeeder::class,
        ]);
    }
}
