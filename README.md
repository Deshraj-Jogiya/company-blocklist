# Company Blocklist

A small real CodeIgniter 4 app (PHP) tracking companies to avoid in a job search --
ghosted, bad-faith offers, etc. -- with a reason and a blocked-at timestamp.

## Endpoints

- `GET /blocked-companies` -- list, newest first.
- `GET /blocked-companies/(:num)` -- one company; 404 if missing.
- `POST /blocked-companies` -- add one (`company_name`, `reason`); 400 on validation
  failure (missing field, or a duplicate `company_name`).
- `DELETE /blocked-companies/(:num)` -- remove one; 404 if missing.

## Running it

```bash
composer install
php spark migrate
php spark serve
```

```bash
curl -X POST http://localhost:8080/blocked-companies \
  -d "company_name=Acme Corp" -d "reason=Ghosted after onsite twice"
curl http://localhost:8080/blocked-companies
```

## Testing

```bash
CI_ENVIRONMENT=testing ./vendor/bin/phpunit tests/feature/BlockedCompaniesTest.php
```

Real feature tests (`FeatureTestTrait` + `DatabaseTestTrait`) hitting real routes
through the real router/controller/model stack against a real (file-based SQLite)
test database, migrated fresh for every test.

Two real CodeIgniter gotchas found and fixed while getting this green:

1. `DatabaseTestTrait::$namespace` defaults to `'Tests'`, so it only auto-migrates
   the test-support namespace by default -- it must be set to `null` explicitly to
   also migrate this app's real `App\Database\Migrations`.
2. `phpunit.dist.xml`'s default `database.tests.database` is `:memory:`, which is
   fine for CodeIgniter's own internal test connection reuse, but was overridden here
   to a real file-based SQLite path for clarity when debugging table-visibility
   issues across connections.
