<?php

declare(strict_types=1);

namespace Tests\Support\Database\Seeds;

use CodeIgniter\Database\Seeder;

class TestSeeder extends Seeder
{
    public function run()
    {
        $rows = [
            ['name' => 'name1'],
            ['name' => 'name2'],
        ];

        // Idempotent: clear before insert so re-running the seeder does not violate UNIQUE(name).
        $this->db->table('test')->truncate();
        $this->db->table('test')->insertBatch($rows);
    }
}
