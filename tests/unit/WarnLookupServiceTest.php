<?php

namespace Tests\Unit;

use App\Libraries\WarnLookupService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Fast, offline tests against a real fixture (tests/_support/Files/
 * warn_report_fixture.xlsx -- a genuine 15-row slice of California's
 * real public WARN Act dataset, not synthetic data) so the core parsing/
 * search logic is covered without a live network call on every run.
 * See WarnLookupServiceLiveTest for the one real network-dependent test.
 */
final class WarnLookupServiceTest extends CIUnitTestCase
{
    private function fixturePath(): string
    {
        return TESTPATH . '_support/Files/warn_report_fixture.xlsx';
    }

    public function testSearchFindsARealCompanyByCaseInsensitiveSubstring(): void
    {
        $service = new WarnLookupService($this->fixturePath());
        $matches = $service->search('bridal');

        $this->assertNotEmpty($matches);
        $this->assertGreaterThanOrEqual(2, count($matches)); // David's Bridal has several real per-location rows
    }

    public function testSearchDecodesRealHtmlEntitiesInCompanyNames(): void
    {
        // The real government file stores "David&rsquo;s Bridal" -- this
        // must come back as a real apostrophe, not the raw entity.
        $service = new WarnLookupService($this->fixturePath());
        $matches = $service->search('bridal');

        $this->assertStringContainsString("David\u{2019}s Bridal", $matches[0]['company']);
        $this->assertStringNotContainsString('&rsquo;', $matches[0]['company']);
    }

    public function testSearchReturnsRealFieldsPerMatch(): void
    {
        $service = new WarnLookupService($this->fixturePath());
        $matches = $service->search('bridal');

        $this->assertArrayHasKey('county', $matches[0]);
        $this->assertArrayHasKey('noticeDate', $matches[0]);
        $this->assertArrayHasKey('layoffType', $matches[0]);
        $this->assertArrayHasKey('employeeCount', $matches[0]);
        $this->assertNotSame('', $matches[0]['county']);
    }

    public function testSearchForAGenuinelyAbsentCompanyReturnsEmpty(): void
    {
        $service = new WarnLookupService($this->fixturePath());
        $this->assertSame([], $service->search('totallynotarealcompanyxyz123'));
    }

    public function testSearchWithBlankCompanyNameReturnsEmptyRatherThanEverything(): void
    {
        $service = new WarnLookupService($this->fixturePath());
        $this->assertSame([], $service->search('   '));
    }

    public function testSearchAgainstAMissingCacheFileReturnsEmptyRatherThanCrashing(): void
    {
        $service = new WarnLookupService(sys_get_temp_dir() . '/definitely_does_not_exist_' . uniqid() . '.xlsx');
        $this->assertSame([], $service->search('bridal'));
    }
}
