<?php

namespace Tests\Support\Database\Seeds;

use CodeIgniter\Database\Seeder;

class TestSeeder extends Seeder
{
    public function run()
    {
        $petition = [
            [
                'name' => 'name1'
            ],
            [
                'name' => 'name2'
            ]
        ];

        // Using Query Builder
        $this->db->table('test')->insertBatch($petition);
    }
}
