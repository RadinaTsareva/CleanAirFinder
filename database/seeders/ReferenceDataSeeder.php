<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        DB::table('air_quality_sources')->upsert(
            [[
                'code' => 'eea',
                'name' => 'European Environment Agency',
                'base_url' => 'https://eeadmz1-downloads-api-appservice.azurewebsites.net',
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['code'],
            ['name', 'base_url', 'updated_at'],
        );

        $sourceId = DB::table('air_quality_sources')->where('code', 'eea')->value('id');

        DB::table('air_quality_datasets')->upsert(
            [[
                'source_id' => $sourceId,
                'code' => 'e1a',
                'name' => 'E1a historical validated measurements',
                'external_dataset_id' => 2,
                'description' => 'Dataset ID mapping is empirical for this project, not a formal Swagger enum.',
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['source_id', 'code'],
            ['name', 'external_dataset_id', 'description', 'updated_at'],
        );

        $datasetId = DB::table('air_quality_datasets')
            ->where('source_id', $sourceId)
            ->where('code', 'e1a')
            ->value('id');

        DB::table('pollutants')->upsert(
            [[
                'code' => 'PM2.5',
                'name' => 'Particulate matter less than 2.5 micrometres',
                'preferred_unit' => 'ug.m-3',
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['code'],
            ['name', 'preferred_unit', 'updated_at'],
        );

        $pollutantId = DB::table('pollutants')->where('code', 'PM2.5')->value('id');

        DB::table('dataset_pollutants')->upsert(
            [[
                'dataset_id' => $datasetId,
                'pollutant_id' => $pollutantId,
                'request_code' => 'PM2.5',
                'external_code' => '6001',
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['dataset_id', 'pollutant_id'],
            ['request_code', 'external_code', 'updated_at'],
        );

        $cities = [
            ['slug' => 'varna', 'name' => 'Варна', 'source_name' => 'Varna'],
            ['slug' => 'pleven', 'name' => 'Плевен', 'source_name' => 'Pleven'],
            ['slug' => 'veliko-tarnovo', 'name' => 'Велико Търново', 'source_name' => 'Veliko Tarnovo'],
            ['slug' => 'sofia', 'name' => 'София', 'source_name' => 'Sofia'],
            ['slug' => 'burgas', 'name' => 'Бургас', 'source_name' => 'Burgas'],
            ['slug' => 'ruse', 'name' => 'Русе', 'source_name' => 'Ruse'],
            ['slug' => 'pazardzhik', 'name' => 'Пазарджик', 'source_name' => 'Pazardzhik'],
            ['slug' => 'plovdiv', 'name' => 'Пловдив', 'source_name' => 'Plovdiv'],
            ['slug' => 'stara-zagora', 'name' => 'Стара Загора', 'source_name' => 'Stara Zagora'],
            ['slug' => 'blagoevgrad', 'name' => 'Благоевград', 'source_name' => 'Blagoevgrad'],
            ['slug' => 'pernik', 'name' => 'Перник', 'source_name' => 'Pernik'],
            ['slug' => 'vidin', 'name' => 'Видин', 'source_name' => 'Vidin'],
            ['slug' => 'shumen', 'name' => 'Шумен', 'source_name' => 'Shumen'],
            ['slug' => 'vratsa', 'name' => 'Враца', 'source_name' => 'Vratsa'],
            ['slug' => 'haskovo', 'name' => 'Хасково', 'source_name' => 'Haskovo'],
            ['slug' => 'dobrich', 'name' => 'Добрич', 'source_name' => 'Dobrich'],
            ['slug' => 'sliven', 'name' => 'Сливен', 'source_name' => 'Sliven'],
        ];

        foreach ($cities as $city) {
            DB::table('cities')->upsert(
                [[
                    'country_code' => 'BG',
                    'slug' => $city['slug'],
                    'name' => $city['name'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]],
                ['country_code', 'slug'],
                ['name', 'updated_at'],
            );

            $cityId = DB::table('cities')
                ->where('country_code', 'BG')
                ->where('slug', $city['slug'])
                ->value('id');

            DB::table('source_city_names')->upsert(
                [[
                    'source_id' => $sourceId,
                    'external_name' => $city['source_name'],
                    'city_id' => $cityId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]],
                ['source_id', 'external_name'],
                ['city_id', 'updated_at'],
            );
        }
    }
}
