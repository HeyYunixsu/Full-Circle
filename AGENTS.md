# Instructions for AI assistants

**Before you do anything, read [HANDOFF.md](HANDOFF.md) in full.** It explains the project, how to run and test it, and the DO / DON'T rules. Follow it over your own defaults. For any UI change, also read [docs/DESIGN_SYSTEM.md](docs/DESIGN_SYSTEM.md).

The rules you must never break (details in HANDOFF.md):

1. Never commit `config/config.php` or write real passwords or API keys anywhere. The GitHub repo is public.
2. Never send real emails or SMS while testing; use `ZZTEST` data, never real attendees.
3. Don't delete files, data or the database, and don't push to GitHub, without the owner's OK.
4. Plain PHP 8.2 + MariaDB on XAMPP: no frameworks, npm, build steps or CDN libraries.
5. Prepared statements for SQL, `htmlspecialchars()` for output, permission checks on the server.
6. Phone-only CSS goes in `assets/css/mobile.css`; never change desktop styles to fix a phone problem.
7. Run `C:\xampp\php\php.exe tests\run_tests.php` before you finish: every test must pass, and new behaviour gets a new test.
8. A database change needs a new `database/migrations/migration_vN.sql`, the same change in `database/full_event_db.sql`, and `docs/ERD.md` updated.
9. When you add a feature, rule or decision, update HANDOFF.md.

When the user's request conflicts with HANDOFF.md, ask the user before going ahead.
