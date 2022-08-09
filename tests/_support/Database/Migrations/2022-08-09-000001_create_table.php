<?php

namespace Tests\Support\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTableTest extends Migration
{
    protected $DBGroup = 'tests';

    public function up()
    {
        $this->forge->addField([
            'id'                    => ['type' => 'int', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'name'            => ['type' => 'varchar', 'constraint' => 255, 'null' => true, 'default' => null],
            'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
            'updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'deleted_at'       => ['type' => 'datetime', 'null' => true, 'default' => null]
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('deleted_at');
        $this->forge->addUniqueKey(['name']);

        $this->forge->createTable('test', true);
    }

    public function down()
    {
        $this->forge->dropTable('test', true);
    }
}
