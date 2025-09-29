<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ExtrasSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['name' => 'Desayuno',            'description' => 'Desayuno buffet',        'price' => 180, 'is_active' => 1],
            ['name' => 'Traslado aeropuerto', 'description' => 'Servicio sencillo',      'price' => 550, 'is_active' => 1],
            ['name' => 'Servicio romántico',  'description' => 'Vino + decoración',      'price' => 900, 'is_active' => 1],
        ];

        foreach ($rows as $r) {
            DB::table('extras')->updateOrInsert(
                ['name' => $r['name']],
                $r + ['updated_at' => now(), 'created_at' => now()]
            );
        }
    }
}
