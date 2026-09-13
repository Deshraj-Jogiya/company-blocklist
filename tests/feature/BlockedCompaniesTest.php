<?php

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Real feature tests: hit real routes through the real CodeIgniter
 * router/controller/model stack against a real (in-memory SQLite) test
 * database, migrated fresh for each test via DatabaseTestTrait.
 */
final class BlockedCompaniesTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate = true;
    protected $refresh = true;
    // DatabaseTestTrait only auto-migrates the 'Tests' namespace by default --
    // null migrates every discovered namespace, including this app's real
    // App\Database\Migrations, which is what actually creates our table.
    protected $namespace = null;

    public function testCreatingABlockedCompanyPersistsItAndListsIt(): void
    {
        $result = $this->post('blocked-companies', [
            'company_name' => 'Acme Corp',
            'reason' => 'Ghosted after onsite twice',
        ]);

        $result->assertStatus(201);
        $this->seeInDatabase('blocked_companies', ['company_name' => 'Acme Corp']);

        $listResult = $this->get('blocked-companies');
        $listResult->assertStatus(200);
        $companies = json_decode($listResult->getJSON(), true);
        $names = array_column($companies, 'company_name');
        $this->assertContains('Acme Corp', $names);
    }

    public function testCreatingWithoutARequiredFieldFails(): void
    {
        $result = $this->post('blocked-companies', ['company_name' => 'Acme Corp']);
        $result->assertStatus(400);
        $this->dontSeeInDatabase('blocked_companies', ['company_name' => 'Acme Corp']);
    }

    public function testDuplicateCompanyNameIsRejected(): void
    {
        $this->post('blocked-companies', ['company_name' => 'Acme Corp', 'reason' => 'first'])
            ->assertStatus(201);

        $second = $this->post('blocked-companies', ['company_name' => 'Acme Corp', 'reason' => 'second']);
        $second->assertStatus(400);
    }

    public function testShowingAMissingCompanyReturns404(): void
    {
        $result = $this->get('blocked-companies/999');
        $result->assertStatus(404);
    }

    public function testDeletingARealCompanyRemovesIt(): void
    {
        $this->post('blocked-companies', ['company_name' => 'Acme Corp', 'reason' => 'test']);
        $row = $this->db->table('blocked_companies')->where('company_name', 'Acme Corp')->get()->getRow();

        $result = $this->delete('blocked-companies/' . $row->id);
        $result->assertStatus(204);
        $this->dontSeeInDatabase('blocked_companies', ['company_name' => 'Acme Corp']);
    }
}
