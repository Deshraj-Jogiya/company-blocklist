<?php

namespace App\Models;

use CodeIgniter\Model;

class BlockedCompanyModel extends Model
{
    protected $table = 'blocked_companies';
    protected $primaryKey = 'id';
    protected $allowedFields = ['company_name', 'reason', 'blocked_at'];
    protected $returnType = 'array';
    protected $useTimestamps = false;
    protected $validationRules = [
        'company_name' => 'required|min_length[1]|is_unique[blocked_companies.company_name,id,{id}]',
        'reason' => 'required|min_length[1]',
    ];
}
