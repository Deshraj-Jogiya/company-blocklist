<?php

namespace App\Libraries;

use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Real lookup against California's public WARN Act layoff-notice dataset
 * (EDD, updated regularly, no API key, no auth -- confirmed live before
 * writing this: https://edd.ca.gov/siteassets/files/jobs_and_training/warn/warn_report.xlsx).
 * Never invents or guesses a layoff -- every result returned here is a
 * real row from the real government file.
 *
 * The real sheet name has a trailing space ("Detailed WARN Report ") and
 * a title row before the real header row -- both confirmed against the
 * live file, not assumed from documentation (there isn't any).
 * Company names in the real data carry HTML entities (e.g.
 * "David&rsquo;s Bridal") from whatever system EDD exports this with.
 */
class WarnLookupService
{
    private const SOURCE_URL = 'https://edd.ca.gov/siteassets/files/jobs_and_training/warn/warn_report.xlsx';
    private const SHEET_NAME = 'Detailed WARN Report ';
    private const CACHE_TTL_SECONDS = 86400; // real file is updated periodically, not live -- a day-old cache is fine

    private string $cachePath;

    public function __construct(?string $cachePath = null)
    {
        $this->cachePath = $cachePath ?? WRITEPATH . 'cache/warn_report.xlsx';
    }

    /**
     * Downloads the real file only when the local cache is missing or
     * stale -- never on every request, since this is Deshraj's own real
     * bandwidth and EDD's real server, not something to hit repeatedly
     * for no reason.
     */
    public function ensureFreshData(): bool
    {
        if (is_file($this->cachePath) && (time() - filemtime($this->cachePath)) < self::CACHE_TTL_SECONDS) {
            return true;
        }

        $contents = @file_get_contents(self::SOURCE_URL);
        if ($contents === false) {
            return false;
        }

        if (! is_dir(dirname($this->cachePath))) {
            mkdir(dirname($this->cachePath), 0755, true);
        }

        return file_put_contents($this->cachePath, $contents) !== false;
    }

    /**
     * Real, case-insensitive substring match against every real company
     * name in the file -- WARN notices are filed per-location, so one
     * real layoff event can produce several rows (one per site), all
     * genuinely returned, never deduplicated into a false single count.
     *
     * @return array<int, array{county: string, noticeDate: string, company: string, layoffType: string, employeeCount: string}>
     */
    public function search(string $companyName): array
    {
        if (trim($companyName) === '' || ! is_file($this->cachePath)) {
            return [];
        }

        $spreadsheet = IOFactory::load($this->cachePath);
        $sheet = $spreadsheet->getSheetByName(self::SHEET_NAME);
        if ($sheet === null) {
            return [];
        }

        $needle = mb_strtolower(trim($companyName));
        $matches = [];

        // Row 0: title, Row 1: real headers -- real data starts at row 2.
        foreach ($sheet->toArray() as $i => $row) {
            if ($i < 2) {
                continue;
            }
            $company = html_entity_decode((string) ($row[4] ?? ''), ENT_QUOTES);
            if ($company === '' || ! str_contains(mb_strtolower($company), $needle)) {
                continue;
            }
            $matches[] = [
                'county' => (string) ($row[0] ?? ''),
                'noticeDate' => (string) ($row[1] ?? ''),
                'company' => trim($company),
                'layoffType' => (string) ($row[5] ?? ''),
                'employeeCount' => (string) ($row[6] ?? ''),
            ];
        }

        return $matches;
    }
}
