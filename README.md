# Company Blocklist

A real CodeIgniter 4 app (PHP) tracking companies to avoid in a job search -- ghosted,
bad-faith offers, layoffs, bad reviews -- with real bulk import and a real cross-check
against California's public WARN Act layoff-notice dataset.

## What it does

- Tracks blocked companies with a reason, a bounded reason category (`layoffs`,
  `bad_reviews`, `no_sponsorship`, `other`), and where the entry came from (manual entry,
  CSV import, or a WARN lookup).
- **Real bulk CSV import** -- upload a CSV with `company_name`/`reason`/optional
  `reason_category` columns; every row goes through the same real validation a manual
  add does, duplicates are skipped (not silently dropped or double-inserted), and the
  response reports exactly what was imported, skipped, and rejected, per row.
- **Real search and filtering** -- `?category=` and `?search=` on the list endpoint.
- **Real cross-check against California's public WARN Act dataset** -- California EDD
  publishes real, free, no-auth-required layoff notices
  (`edd.ca.gov/siteassets/files/jobs_and_training/warn/warn_report.xlsx`, confirmed
  live). `GET /blocked-companies/warn-check?company=X` searches the real, current file
  (cached locally for 24h so it isn't re-downloaded on every request) and returns real
  matching notices -- county, date, layoff type, employee count -- so a human can decide
  whether to add the company, never auto-added.

## Endpoints

- `GET /blocked-companies?category=&search=` -- list, filterable by real category and a
  real substring search over company name and reason.
- `GET /blocked-companies/(:num)` -- one company; 404 if missing.
- `POST /blocked-companies` -- add one (`company_name`, `reason`, optional
  `reason_category`); 400 on validation failure (missing field, invalid category, or a
  duplicate `company_name`).
- `POST /blocked-companies/import` -- bulk-add from an uploaded CSV file
  (`company_name`, `reason` columns required, `reason_category` optional). Returns
  `importedCount`/`skippedCount`/`rejectedCount` plus the real per-row detail.
- `GET /blocked-companies/warn-check?company=X` -- real matches from California's live
  WARN Act dataset for a company name.
- `DELETE /blocked-companies/(:num)` -- remove one; 404 if missing.

## Running it

```bash
composer install
php spark migrate
php spark serve
```

```bash
curl -X POST http://localhost:8080/blocked-companies \
  -d "company_name=Acme Corp" -d "reason=Ghosted after onsite twice" -d "reason_category=bad_reviews"
curl -X POST http://localhost:8080/blocked-companies/import -F "file=@companies.csv"
curl "http://localhost:8080/blocked-companies/warn-check?company=Acme"
curl "http://localhost:8080/blocked-companies?category=layoffs&search=corp"
```

## Testing

```bash
CI_ENVIRONMENT=testing ./vendor/bin/phpunit
```

Real feature tests (`FeatureTestTrait` + `DatabaseTestTrait`) hitting real routes
through the real router/controller/model stack against a real (file-based SQLite) test
database, migrated fresh for every test, including real CSV file uploads through the
real `$_FILES` superglobal.

`WarnLookupService` has two layers of coverage: fast, offline tests against a real
15-row fixture (`tests/_support/Files/warn_report_fixture.xlsx` -- a genuine slice of
the live dataset, not synthetic data, including its real HTML-entity-encoded company
names), plus a separate, real live-network test suite confirming the actual government
URL is reachable and returns real, parseable data. Verified directly before writing any
code: the live file's real sheet name has a trailing space
(`"Detailed WARN Report "`), and its real header row is row 2, not row 1.

Real CodeIgniter gotchas found and fixed while getting this green:

1. `DatabaseTestTrait::$namespace` defaults to `'Tests'`, so it only auto-migrates
   the test-support namespace by default -- it must be set to `null` explicitly to
   also migrate this app's real `App\Database\Migrations`.
2. `phpunit.dist.xml`'s default `database.tests.database` is `:memory:`, which is
   fine for CodeIgniter's own internal test connection reuse, but was overridden here
   to a real file-based SQLite path for clarity when debugging table-visibility
   issues across connections.
