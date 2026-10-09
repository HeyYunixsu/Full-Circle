# HANDOFF — read this before changing anything

For anyone (person or AI assistant) working on this project. It says what the project is, how to run and test it, and the rules: what to do and what not to do. The code is the source of truth: if this file and the code disagree, trust the code and fix this file.

Last updated: 2026-10-09.

## 1. The project

- **What:** web-based event check-in system with QR codes and real-time monitoring for **Full Circle Events Asia, Inc.** (always this exact name; never drop "Asia").
- **Why:** capstone project, STI College Global City. The defense checks Objective 3 (automated notifications) and Objective 4 (documentation and testing: traceability matrix + test cases in `docs/`).
- **Stack:** plain PHP 8.2 + MariaDB 10.4 on XAMPP for Windows. No framework, no build step, no npm. Third-party PHP libraries are copied into `vendor/` (PHPMailer, SimpleXLSX, phpqrcode).
- **Language:** screens and code comments are in English. Some docs in `docs/` and `cron/README.md` are in Taglish; keep each file's language.

## 2. Run it

1. Put the code at `C:\xampp\htdocs\Full_Event` (folder name must be exactly `Full_Event`).
2. Copy `config/config.example.php` to `config/config.php`.
3. In phpMyAdmin, import `database/full_event_db.sql` (it creates `full_event_db` itself).
4. Turn on `extension=gd` in `C:\xampp\php\php.ini` (QR images), restart Apache.
5. Open http://localhost/Full_Event. Demo accounts (password `admin123`): `superadmin@fullcircle.com`, `admin@fullcircle.com`, `staff@fullcircle.com`. Passkeys for the Register page: `STAFF2026`, `ADMIN2026`, `SUPER2026`.

The zip package does steps 1 to 4 with `setup.bat`; see its `SETUP-GUIDE.txt`.

**Tests:** `C:\xampp\php\php.exe tests\run_tests.php` (needs Apache + MySQL running). 59 tests: unit, integration and HTTP system tests. They create `ZZTEST` records, delete them afterwards and never send email or SMS. All must pass before you commit.

## 3. Structure

```
config/config.php        settings + DB connection + session. NOT in Git (has passwords); config.example.php is the template
core/bootstrap.php       the one require every page uses (config, helpers, auth, flash, logger, stats, icons) + ASSET_VER
core/helpers.php         sanitize() (trim only), capitalizeWords(), csrfField()/checkCsrf(), qrFilePath(), isValidEventDate(), formatDate/Time
core/auth.php            requireLogin(), requireRole(), canEditAttendee(), canCreateRole(), isEventLocked()
core/flash.php           redirect($url, $msg, $type) and getFlashMessage() (the pop-up after an action)
core/stats.php           getEventStats(), selectedEvent(), companyCapacity(), checkinClosedReason(), markOngoingIfEventDay(),
                         overviewEvents(), badgeTemplateFor(), findEvent()
includes/                header, sidebar, icons, mailer (PHPMailer + email templates), sms (Semaphore), qrcode, excel_parser,
                         automation (reminders + feedback links), badge_picker
modules/SessionManager.php   breakout sessions (seminars), not login sessions
pages/<area>/            the screens: auth, dashboard, events, attendees, checkin, badges, sessions, feedback, reports, companies, accounts, qr
api/<area>/              endpoints the pages call (JSON or redirect)
assets/css/style.css     all desktop styles + design tokens;  assets/css/mobile.css  phone-only styles
assets/js/dialog.js      confirm dialog, appToast() pop-ups, double-submit guard (loaded on every page)
assets/js/badge-render.js    renderBadge(): the one badge drawing used by print, previews and lists
cron/notify.php          hourly job: day-before reminders + feedback links (not scheduled yet, see open items)
database/full_event_db.sql   complete fresh database (13 tables + demo accounts + 11 badge designs)
database/migrations/     upgrade steps for older databases, v3 to v9
docs/                    ERD, objectives traceability, test cases, DESIGN_SYSTEM.md, SMS/email setup
tests/run_tests.php      automated tests
```

**Roles:** `super_admin` (everything, manages admins and staff, makes passkeys), `admin` (events, sessions, reports, feedback, companies, manages staff), `staff` (dashboard, badge designer, check-in while an event is ongoing).

## 4. DO

- **Read `docs/DESIGN_SYSTEM.md` before any UI change.** Use the tokens in `style.css :root` (`--color-*`, `--space-*`, `--text-*`) and the shared components; no raw hex colours in new code.
- **Follow the page pattern:** `require core/bootstrap.php` → `requireLogin()` or `requireRole([...])` → handle POST and `redirect()` → query → HTML with `includes/sidebar.php` and `includes/header.php`. Back arrow: set `$back_url` and `$back_label` before including the header.
- **SQL: prepared statements only.** `sanitize()` only trims; store text exactly as typed. Names, companies and places go through `capitalizeWords()`.
- **Escape every output** with `htmlspecialchars()`. Inside `<script>`, use `json_encode(..., JSON_HEX_TAG)`.
- **Check permissions on the server** in every endpoint (`requireRole`, `canEditAttendee`), not only by hiding buttons.
- **Check-in rules live in one place:** `checkinClosedReason($event)` (open while Ongoing or on the event day) and `markOngoingIfEventDay()`. Every check-in path uses them.
- **Messages to the user:** after a page action use `redirect($url, $message, 'success'|'error'|'warning')`. From JavaScript use `appToast(message, type)`, or `appToastNext()` before a reload. Confirmations: `data-confirm="..."` on a link, button or form, or `appConfirm()` in JS.
- **Phone fixes go in `assets/css/mobile.css` only**, scoped by the page's body class (`page-attendees`, `page-scan`, ...). New pages link it after style.css: `<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/mobile.css?v=<?= ASSET_VER ?>" media="(max-width: 768px)">`.
- **New CSS or JS file:** add it to `ASSET_VER` in `core/bootstrap.php` so browsers load the new version.
- **Badges:** draw them only with `renderBadge()` from `badge-render.js`. The design printed for an event comes from `badgeTemplateFor()`.
- **Database change:** add `database/migrations/migration_vN.sql` (next number, safe to run twice: `IF NOT EXISTS`), apply the same change to `database/full_event_db.sql`, update `docs/ERD.md`, and tell the team to run the migration.
- **New behaviour gets a test** in `tests/run_tests.php` (use `ZZTEST` names; the cleanup deletes them).
- **Keep forms consistent:** labels without asterisks, `(optional)` on optional fields, values kept after an error, buttons right-aligned in `.form-actions`.
- **Update this file** when you add a feature, a rule or a decision.

## 5. DON'T

- **Never commit `config/config.php`** or paste real passwords or API keys (Gmail app password, Semaphore key) into code, docs, commits or chat. The repo is public.
- **Never send real emails or SMS while testing.** Keep `SMS_MOCK` true unless the team decides otherwise. Test with `ZZTEST` data, never with real attendees.
- **Don't delete files, events, attendees or the database without asking the owner.** Dead files go to `_unused/` (not in Git) instead of being deleted.
- **Don't push to GitHub without the team's OK.**
- **Don't add frameworks, npm, build steps or CDN libraries.** Plain PHP, CSS and JS.
- **Don't edit desktop CSS to fix a phone problem** (use mobile.css).
- **Don't reintroduce purple** (#7367F0) or a second palette; it's wine/magenta only.
- **Don't use `confirm()` or `alert()`**, and don't put HTML in flash messages (they are escaped).
- **Don't name a page variable `$live`**: `includes/sidebar.php` sets it.
- **Don't change the login page design**; the client likes it as it is.
- **Don't bypass `checkinClosedReason()`** or add a check-in path that skips it.

## 6. Features worth knowing (2026-10)

- **Check-in:** QR scan (camera or typed 9-digit code), walk-in registration, attendee list with live updates. Undo a wrong check-in (`api/checkin/undo.php`): staff within 5 minutes, admins any time.
- **Walk-in desk:** after printing a walk-in's badge the page returns to a blank walk-in form ("Next Walk-in"); the scan desk returns to the scanner. Same-name warning ("Is this the same person?"). Optional QR copy by email and/or SMS, both unticked by default.
- **Badges:** each event picks a design when created (changeable on the event page). 11 designs ship with the database. The badge page prints the event's design, lets staff fix a wrong name before printing, and shrinks long names to fit.
- **Dashboard:** totals for all live events (or a switcher per event), a clickable calendar listing each day's events.
- **Security:** 5 wrong passwords lock an account for 15 minutes. The session cookie is `SameSite=Strict`; new POST forms also use `csrfField()` / `checkCsrf()`.
- **Automation (Objective 3):** `includes/automation.php` + `cron/notify.php`: day-before reminder (email + SMS) and feedback links after an event; deduplicated through `email_queue` / `sms_queue`, failures retried up to 3 times.
- **SMS:** Semaphore (`includes/sms.php`). Mock mode simulates sending; the screens no longer say "mock".

## 7. Decisions already made (don't re-ask)

- Brand: "Full Circle Events Asia, Inc."; one wine/magenta palette.
- SMS provider: Semaphore. Reminders go out one day before (email and SMS).
- Cron: a single hourly `cron/notify.php`.
- CSRF: SameSite=Strict cookie, plus tokens on new forms.
- Badge design is chosen per event, not per badge.
- Company limits are counters only; walk-ins and uploads are not blocked when a company is full.
- Walk-in QR copies are opt-in (unticked).

## 8. Open items

1. **Hourly task not scheduled.** It sends real emails. When the team approves: `schtasks /Create /TN "Full_Event Notify" /SC HOURLY /TR "C:\xampp\php\php.exe C:\xampp\htdocs\Full_Event\cron\notify.php"`.
2. **Semaphore sender name** awaits approval. After that: set `SEMAPHORE_SENDER_NAME`, `SMS_MOCK = false`, test with `cron/test_sms.php`.
3. **Team paperwork:** paste the exact Chapter 1 objective wording into `docs/objectives-traceability.md`; run manual tests M01–M16 and UAT01–UAT10 in `docs/test-cases.md`.
4. **Ideas not built yet:** Data Privacy consent checkbox + deleting personal data of old archived events; blocking walk-ins when a company/event is full; auto-logout on idle laptops; month arrows on the dashboard calendar; bundling Jakarta Sans / Montserrat fonts for the badge designer.
5. `docs/ERD.png` (the picture) doesn't show `events.badge_template_id` or the login-lock columns yet; `docs/ERD.md` does.
6. Change the demo passwords (`admin123`) before any real event.
