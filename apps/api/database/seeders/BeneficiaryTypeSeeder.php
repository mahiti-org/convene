<?php

namespace Database\Seeders;

use App\Models\BeneficiaryType;
use Illuminate\Database\Seeder;

/**
 * Institutional and individual beneficiary types.
 */
class BeneficiaryTypeSeeder extends Seeder
{
    public function run(): void
    {
        $institutional = [
            ['code' => 'household', 'label' => 'Household'],
            ['code' => 'school', 'label' => 'School'],
            ['code' => 'anganwadi', 'label' => 'Anganwadi'],
            ['code' => 'health_facility', 'label' => 'Health Facility'],
            ['code' => 'group', 'label' => 'Group'],
            ['code' => 'fpo', 'label' => 'FPO'],
        ];

        $individual = [
            ['code' => 'men', 'label' => 'Men'],
            ['code' => 'women', 'label' => 'Women'],
            ['code' => 'children', 'label' => 'Children'],
            ['code' => 'adolescents', 'label' => 'Adolescents'],
            ['code' => 'mothers', 'label' => 'Mothers'],
            ['code' => 'farmers', 'label' => 'Farmers'],
        ];

        foreach ($institutional as $type) {
            BeneficiaryType::query()->firstOrCreate(
                ['code' => $type['code']],
                ['class' => BeneficiaryType::CLASS_INSTITUTIONAL, 'label' => $type['label'], 'is_system' => true],
            );
        }

        foreach ($individual as $type) {
            BeneficiaryType::query()->firstOrCreate(
                ['code' => $type['code']],
                ['class' => BeneficiaryType::CLASS_INDIVIDUAL, 'label' => $type['label'], 'is_system' => true],
            );
        }
    }
}
