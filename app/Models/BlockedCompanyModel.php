<?php

namespace App\Models;

use CodeIgniter\Model;

class BlockedCompanyModel extends Model
{
    protected $table = 'blocked_companies';
    protected $primaryKey = 'id';
    protected $allowedFields = ['company_name', 'reason', 'reason_category', 'source', 'blocked_at'];
    protected $returnType = 'array';
    protected $useTimestamps = false;

    // A real, bounded set -- kept in one place so the model, the
    // controller's import validation, and any future UI dropdown all
    // read from the same real list instead of drifting apart.
    public const REASON_CATEGORIES = ['layoffs', 'bad_reviews', 'no_sponsorship', 'other'];

    protected $validationRules = [
        'company_name' => 'required|min_length[1]|is_unique[blocked_companies.company_name,id,{id}]',
        'reason' => 'required|min_length[1]',
        'reason_category' => 'permit_empty|in_list[layoffs,bad_reviews,no_sponsorship,other]',
    ];
}
