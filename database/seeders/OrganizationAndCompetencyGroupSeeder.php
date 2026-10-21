<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrganizationAndCompetencyGroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $organizations = [
            'LOB MSM BPO',
            'LOB MSM LO',
            'LOB SM',
            'LOB SALES',
            'LOB CCCM',
            'LOB JAGOTI',
            'LOB SECURITY',
            'DIV HCGS CORDEV',
            'DIV FINANCE',
            'DIV OSM',
            'DIV SRNM',
            'DIV CLIR',
            'DIV IT',
            'DIV MARKETING',
        ];

        DB::transaction(function () use ($organizations): void {
            $timestamp = now();

            DB::table('organizations')->upsert(
                array_map(fn (string $name): array => [
                    'code' => Str::slug($name),
                    'name' => $name,
                    'is_active' => true,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ], $organizations),
                ['code'],
                ['name', 'updated_at'],
            );

            DB::table('competency_groups')->upsert(
                array_map(fn (string $code): array => [
                    'code' => $code,
                    'name' => "Kelompok {$code}",
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ], range('A', 'F')),
                ['code'],
                ['name', 'updated_at'],
            );
        });
    }
}
