<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            AdminPermissionSeeder::class,
            ProjectAccessSeeder::class,
            ScoringPermissionSeeder::class,
            RubricPermissionSeeder::class,
            RubricSeeder::class,
            PeopleReportPermissionSeeder::class,
            DashboardPermissionSeeder::class,
            SystemSettingSeeder::class,
            OrganizationAndCompetencyGroupSeeder::class,
            CompetencyMasterSeeder::class,
            QccStepSeeder::class,
        ]);
    }
}
