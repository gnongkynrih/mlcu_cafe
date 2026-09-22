<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\CafeTable;
class CafeTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        for($i=1;$i<=10;$i++){
            CafeTable::create([
                'name'=>'T-'. $i,
                'capacity'=>rand(1,5),
            ]);
        }
    }
}
