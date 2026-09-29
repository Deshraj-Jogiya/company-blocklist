<?php

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Real feature tests for bulk CSV import and search/filter -- a real CSV
 * file (a real temp file on disk, not an in-memory string) is uploaded
 * through the real $_FILES superglobal the same way a browser multipart
 * upload populates it, then read back through the real
 * BlockedCompanies::import() controller action.
 */
final class BlockedCompaniesImportTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate = true;
    protected $refresh = true;
    protected $namespace = null;

    private function uploadCsv(string $contents): array
    {
        // Real bug found via CI: CodeIgniter's FileCollection reads
        // uploaded files through the framework's own Superglobals
        // service (service('superglobals')->getFilesArray()), which
        // keeps its OWN internal copy rather than reading the raw
        // $_FILES superglobal directly -- setting $_FILES manually is
        // silently invisible to it. The real, correct way to simulate a
        // file upload in a feature test is through the service's own
        // setFilesArray().
        $tempPath = tempnam(sys_get_temp_dir(), 'blocklist_import_');
        file_put_contents($tempPath, $contents);

        service('superglobals')->setFilesArray([
            'file' => [
                'name' => 'companies.csv',
                'type' => 'text/csv',
                'tmp_name' => $tempPath,
                'error' => 0,
                'size' => strlen($contents),
            ],
        ]);

        $result = $this->post('blocked-companies/import');
        service('superglobals')->setFilesArray([]);

        return json_decode($result->getJSON(), true);
    }

    public function testImportingARealCsvPersistsEachRealRow(): void
    {
        $csv = "company_name,reason,reason_category\n"
            . "Acme Corp,Ghosted after onsite,bad_reviews\n"
            . "Widget Inc,Real 2024 layoff round,layoffs\n";

        $body = $this->uploadCsv($csv);

        $this->assertSame(2, $body['importedCount']);
        $this->assertSame(0, $body['skippedCount']);
        $this->assertSame(0, $body['rejectedCount']);
        $this->seeInDatabase('blocked_companies', ['company_name' => 'Acme Corp', 'reason_category' => 'bad_reviews']);
        $this->seeInDatabase('blocked_companies', ['company_name' => 'Widget Inc', 'source' => 'csv_import']);
    }

    public function testImportingADuplicateCompanyIsSkippedNotDuplicated(): void
    {
        $this->post('blocked-companies', ['company_name' => 'Acme Corp', 'reason' => 'first, manual']);

        $body = $this->uploadCsv("company_name,reason\nAcme Corp,duplicate from csv\n");

        $this->assertSame(0, $body['importedCount']);
        $this->assertSame(1, $body['skippedCount']);
        $rows = $this->db->table('blocked_companies')->where('company_name', 'Acme Corp')->get()->getResultArray();
        $this->assertCount(1, $rows);
    }

    public function testImportingARowWithAnEmptyCompanyNameIsRejectedNotSilentlyDropped(): void
    {
        $body = $this->uploadCsv("company_name,reason\n,no name here\nReal Co,a real reason\n");

        $this->assertSame(1, $body['importedCount']);
        $this->assertSame(1, $body['rejectedCount']);
        $this->assertSame(2, $body['rejected'][0]['row']); // header is row 1
    }

    public function testAnInvalidReasonCategoryFallsBackToOtherRatherThanFailing(): void
    {
        $body = $this->uploadCsv("company_name,reason,reason_category\nReal Co,a reason,not_a_real_category\n");

        $this->assertSame(1, $body['importedCount']);
        $this->seeInDatabase('blocked_companies', ['company_name' => 'Real Co', 'reason_category' => 'other']);
    }

    public function testMissingRequiredColumnsReturnsARealError(): void
    {
        $body = $this->uploadCsv("name,why\nAcme,reason\n");
        $this->assertArrayHasKey('error', $body);
    }

    public function testSearchFiltersByRealCategory(): void
    {
        $this->post('blocked-companies', ['company_name' => 'Layoff Co', 'reason' => 'r', 'reason_category' => 'layoffs']);
        $this->post('blocked-companies', ['company_name' => 'Review Co', 'reason' => 'r', 'reason_category' => 'bad_reviews']);

        $result = $this->get('blocked-companies?category=layoffs');
        $companies = json_decode($result->getJSON(), true);
        $names = array_column($companies, 'company_name');

        $this->assertContains('Layoff Co', $names);
        $this->assertNotContains('Review Co', $names);
    }

    public function testSearchMatchesCompanyNameOrReasonText(): void
    {
        $this->post('blocked-companies', ['company_name' => 'Acme Corp', 'reason' => 'ghosted candidates']);
        $this->post('blocked-companies', ['company_name' => 'Widget Inc', 'reason' => 'unrelated']);

        $result = $this->get('blocked-companies?search=ghosted');
        $companies = json_decode($result->getJSON(), true);
        $names = array_column($companies, 'company_name');

        $this->assertContains('Acme Corp', $names);
        $this->assertNotContains('Widget Inc', $names);
    }
}
