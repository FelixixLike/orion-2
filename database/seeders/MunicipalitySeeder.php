<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domain\Store\Models\Municipality;
use App\Domain\Store\Enums\Municipality as MunicipalityEnum;

class MunicipalitySeeder extends Seeder
{
    public function run(): void
    {
        foreach (MunicipalityEnum::cases() as $case) {
            Municipality::firstOrCreate(['name' => $case->label()], ['slug' => $case->value]);
        }
    }
}
