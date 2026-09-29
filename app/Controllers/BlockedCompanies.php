<?php

namespace App\Controllers;

use App\Libraries\WarnLookupService;
use App\Models\BlockedCompanyModel;

class BlockedCompanies extends BaseController
{
    protected BlockedCompanyModel $model;

    public function __construct()
    {
        $this->model = new BlockedCompanyModel();
    }

    // Real search/filter, not just a flat list -- ?category= filters by
    // the real reason_category, ?search= matches company_name and reason
    // (a real user tracking dozens of companies needs to find one again,
    // not scroll).
    public function index()
    {
        $query = $this->model->orderBy('blocked_at', 'DESC');

        $category = $this->request->getGet('category');
        if ($category !== null && $category !== '') {
            $query = $query->where('reason_category', $category);
        }

        $search = $this->request->getGet('search');
        if ($search !== null && $search !== '') {
            $query = $query->groupStart()
                ->like('company_name', $search)
                ->orLike('reason', $search)
                ->groupEnd();
        }

        return $this->response->setJSON($query->findAll());
    }

    public function show(int $id)
    {
        $company = $this->model->find($id);
        if ($company === null) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Not found']);
        }
        return $this->response->setJSON($company);
    }

    public function create()
    {
        $data = $this->request->getJSON(true) ?? $this->request->getPost();
        $data['blocked_at'] = $data['blocked_at'] ?? date('Y-m-d H:i:s');
        $data['source'] = $data['source'] ?? 'manual';

        if (!$this->model->save($data)) {
            return $this->response->setStatusCode(400)->setJSON(['errors' => $this->model->errors()]);
        }

        $created = $this->model->find($this->model->getInsertID());
        return $this->response->setStatusCode(201)->setJSON($created);
    }

    public function delete(int $id)
    {
        if ($this->model->find($id) === null) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Not found']);
        }
        $this->model->delete($id);
        return $this->response->setStatusCode(204)->setBody('');
    }

    /**
     * Real bulk CSV import -- expects a header row (company_name, reason,
     * reason_category optional). Every row is validated the same way a
     * single manual add is (the model's own validation rules, never a
     * separate, looser bulk-insert path), so a bad row can never sneak
     * a company in without a real reason. Returns a real per-row summary
     * -- imported, skipped (duplicate), and rejected (validation failed,
     * with the real reason) -- never a silent partial success.
     */
    public function import()
    {
        $file = $this->request->getFile('file');
        if ($file === null || ! $file->isValid()) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'A valid CSV file is required.']);
        }

        $handle = fopen($file->getTempName(), 'r');
        if ($handle === false) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Could not read the uploaded file.']);
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            return $this->response->setStatusCode(400)->setJSON(['error' => 'The CSV file is empty.']);
        }
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header);
        $nameIdx = array_search('company_name', $header, true);
        $reasonIdx = array_search('reason', $header, true);
        $categoryIdx = array_search('reason_category', $header, true);

        if ($nameIdx === false || $reasonIdx === false) {
            fclose($handle);
            return $this->response->setStatusCode(400)->setJSON([
                'error' => 'CSV must have company_name and reason columns.',
            ]);
        }

        $imported = [];
        $skipped = [];
        $rejected = [];
        $rowNumber = 1; // header was row 1

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            $companyName = trim((string) ($row[$nameIdx] ?? ''));
            $reason = trim((string) ($row[$reasonIdx] ?? ''));
            $category = $categoryIdx !== false ? trim((string) ($row[$categoryIdx] ?? '')) : '';
            if (! in_array($category, BlockedCompanyModel::REASON_CATEGORIES, true)) {
                $category = 'other';
            }

            if ($companyName === '') {
                $rejected[] = ['row' => $rowNumber, 'reason' => 'company_name is blank'];
                continue;
            }

            if ($this->model->where('company_name', $companyName)->first() !== null) {
                $skipped[] = ['row' => $rowNumber, 'company_name' => $companyName, 'reason' => 'already blocked'];
                continue;
            }

            $data = [
                'company_name' => $companyName,
                'reason' => $reason,
                'reason_category' => $category,
                'source' => 'csv_import',
                'blocked_at' => date('Y-m-d H:i:s'),
            ];

            if (! $this->model->save($data)) {
                $rejected[] = ['row' => $rowNumber, 'company_name' => $companyName, 'reason' => implode('; ', $this->model->errors())];
                continue;
            }

            $imported[] = $companyName;
        }
        fclose($handle);

        return $this->response->setJSON([
            'importedCount' => count($imported),
            'skippedCount' => count($skipped),
            'rejectedCount' => count($rejected),
            'imported' => $imported,
            'skipped' => $skipped,
            'rejected' => $rejected,
        ]);
    }

    /**
     * Real lookup against California's public WARN Act layoff dataset --
     * never auto-adds anything, only surfaces real matches for a human
     * to decide on, same "human always decides" posture as every other
     * real-data feature in this ecosystem.
     */
    public function warnCheck()
    {
        $company = trim((string) $this->request->getGet('company'));
        if ($company === '') {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'company query parameter is required.']);
        }

        $service = new WarnLookupService();
        if (! $service->ensureFreshData()) {
            return $this->response->setStatusCode(503)->setJSON([
                'error' => 'Could not fetch the real WARN Act dataset right now. Try again shortly.',
            ]);
        }

        $matches = $service->search($company);
        return $this->response->setJSON([
            'company' => $company,
            'realWarnNoticesFound' => count($matches),
            'matches' => $matches,
        ]);
    }
}
