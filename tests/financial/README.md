# Financial service integration tests

Development-only tests use the dedicated Nodevora LocalWP installation, a private restorable database backup and the bundled Local PHP/WP-CLI. They refuse another site URL. No test route, schema installation or migration runs on plugin activation.

Set `NODEVORA_TEST_SOURCE` to this checkout, then execute `foundation.php` through WP-CLI. `concurrency.py --driver-dir <mission-03/evidence> --output <outside-repo.json>` launches two independent PHP/MySQL processes per case. Do not put real credentials or SQL dumps in the repository.

Current scope: wallet transactions, lots, holds, referenced restoration caps/debt, SQL rollback, lost COMMIT acknowledgment and three database races. Tests bootstrap synthetic ready wallets directly; they do not establish migration or checkout readiness. Customer-facing integration is not enabled. PHP 8.3 syntax/runtime tested; PHP 7.4 runtime still requires validation.
