<?php

namespace Tests\Unit;

use App\Libraries\WarnLookupService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * The one real, live-network test -- confirms ensureFreshData() can
 * actually reach California EDD's real, public WARN Act file (no API
 * key, no auth, confirmed live before this feature was built at all).
 * Kept separate from WarnLookupServiceTest so the fast fixture-based
 * suite never depends on network access, matching this app's existing
 * separation between fast unit coverage and real live verification.
 */
final class WarnLookupServiceLiveTest extends CIUnitTestCase
{
    public function testEnsureFreshDataDownloadsTheRealGovernmentFile(): void
    {
        $cachePath = sys_get_temp_dir() . '/warn_live_test_' . uniqid() . '.xlsx';
        $service = new WarnLookupService($cachePath);

        $ok = $service->ensureFreshData();

        $this->assertTrue($ok, 'Could not reach the real EDD WARN dataset -- check network access or the source URL.');
        $this->assertFileExists($cachePath);
        $this->assertGreaterThan(10000, filesize($cachePath)); // a real file, not an error page

        @unlink($cachePath);
    }

    public function testARealSearchAgainstTheLiveDownloadedFileReturnsPlausibleResults(): void
    {
        $cachePath = sys_get_temp_dir() . '/warn_live_test_' . uniqid() . '.xlsx';
        $service = new WarnLookupService($cachePath);
        $service->ensureFreshData();

        // A genuinely common word across many real, distinct employers'
        // official names -- not asserting an exact count (the real file
        // changes over time), just that real parsing against the live
        // file produces real, structured results.
        $matches = $service->search('inc');

        $this->assertNotEmpty($matches);
        $this->assertArrayHasKey('company', $matches[0]);
        $this->assertArrayHasKey('county', $matches[0]);

        @unlink($cachePath);
    }
}
