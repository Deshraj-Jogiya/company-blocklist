<?php

namespace App\Controllers;

use App\Models\BlockedCompanyModel;

class BlockedCompanies extends BaseController
{
    protected BlockedCompanyModel $model;

    public function __construct()
    {
        $this->model = new BlockedCompanyModel();
    }

    public function index()
    {
        return $this->response->setJSON($this->model->orderBy('blocked_at', 'DESC')->findAll());
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
}
