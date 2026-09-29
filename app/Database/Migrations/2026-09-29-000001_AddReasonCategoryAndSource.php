<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddReasonCategoryAndSource extends Migration
{
    public function up()
    {
        $this->forge->addColumn('blocked_companies', [
            // A real, bounded set of reasons, not free text alone -- lets
            // the list actually be filtered/grouped by why a company was
            // blocked, not just searched by name.
            'reason_category' => [
                'type'       => 'VARCHAR',
                'constraint' => 32,
                'default'    => 'other',
                'after'      => 'reason',
            ],
            // How this entry got here: 'manual' (typed in by hand),
            // 'csv_import' (bulk import), or 'warn_lookup' (matched against
            // a real public WARN Act layoff notice). Real provenance, not
            // guessed after the fact.
            'source' => [
                'type'       => 'VARCHAR',
                'constraint' => 32,
                'default'    => 'manual',
                'after'      => 'reason_category',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('blocked_companies', ['reason_category', 'source']);
    }
}
