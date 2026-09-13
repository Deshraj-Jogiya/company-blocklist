<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateBlockedCompanies extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'company_name' => ['type' => 'VARCHAR', 'constraint' => 255],
            'reason' => ['type' => 'TEXT'],
            'blocked_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('company_name');
        $this->forge->createTable('blocked_companies');
    }

    public function down()
    {
        $this->forge->dropTable('blocked_companies');
    }
}
