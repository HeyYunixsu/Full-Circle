# Objectives Traceability Matrix

Full Circle Events Asia, Inc. : Event Check-in System

Ipinapakita kung saan nakalagay sa code ang bawat objective at kung aling test ang nagpapatunay na gumagana ito. Test IDs: `test-cases.md` (U = unit, I = integration, S = automated system, M = manual system, UAT = user acceptance).

> **Paalala sa team:** Ang wording ng Objective 1 at 2 sa ibaba ay hinango sa title ng system. Palitan ito ng eksaktong wording mula sa Chapter 1 ng paper ninyo, pero huwag galawin ang mga requirement rows; tugma ang mga iyon sa code.

## Objectives

| # | Specific objective |
|---|---|
| **General** | Develop a web-based event check-in system with QR code and real-time monitoring for Full Circle Events Asia, Inc. |
| **Obj 1** | Registration and QR: import attendee lists, generate a unique QR code for every attendee, send it by email and SMS, and register walk-ins. |
| **Obj 2** | QR check-in and real-time monitoring: scan and verify QR codes at the entrance, print badges, and show live check-in counts per event, company and session. |
| **Obj 3** | Automated notifications: send day-before reminders (email and SMS) and post-event feedback links automatically, without manual sending. |
| **Obj 4** | Test and document the system: unit, integration, system and user acceptance testing, with this matrix and the ERD. |
| **Supporting** | Security and administration: role-based access (Super Admin, Admin, Staff), accounts, passkeys, audit trail, reports. |

## Matrix

| Req ID | Requirement | Obj | Module / files | Test cases | Status |
|---|---|---|---|---|---|
| FR-01 | Import attendees mula sa CSV o Excel, may preview at duplicate check | 1 | `pages/attendees/upload.php`, `includes/excel_parser.php` | U13, U14, I02, I03, M06, UAT02 | Done |
| FR-02 | Unique attendee code at QR bawat attendee | 1 | `core/helpers.php` (`generateAttendeeCode`, `generateQRData`), `includes/qrcode.php` | U08, I01, S14 | Done |
| FR-03 | Ipadala ang QR sa email at SMS | 1 | `includes/mailer.php`, `includes/sms.php`, `api/notifications/*`, `pages/attendees/send_progress.php` | U01–U06, M07, UAT03 | Done (SMS: hinihintay ang Semaphore sender name) |
| FR-04 | Public QR page ng attendee (link sa email) | 1 | `pages/qr/view.php` | S14 | Done |
| FR-05 | Walk-in registration | 1 | `pages/checkin/walkin.php` | U01–U04, M09, UAT05 | Done |
| FR-06 | Companies at attendee limit bawat company | 1 | `pages/companies/index.php`, `core/stats.php` (`companyCapacity`, `capacityBadge`) | U10, I05, M05, UAT01 | Done |
| FR-07 | QR scan check-in na may verify step at double-scan protection | 2 | `pages/checkin/scan.php`, `api/checkin/checkin.php` | S06–S10, S12, M08, UAT04 | Done |
| FR-08 | Manual check-in gamit ang attendee code o search | 2 | `api/checkin/checkin.php`, `pages/attendees/index.php` | S09, UAT06 | Done |
| FR-09 | Badge preview, print at Badge Designer | 2 | `pages/checkin/badge.php`, `pages/badges/*`, `api/badges/*` | M10, UAT05 | Done |
| FR-10 | Real-time check-in dashboard (updates every 2 seconds) | 2 | `pages/checkin/index.php`, `api/attendees/poll.php`, `core/stats.php` (`getEventStats`) | I04, S11, M11, UAT07 | Done |
| FR-11 | Sessions at session attendance | 2 | `pages/sessions/*`, `modules/SessionManager.php`, `api/sessions/*` | I06, M12 | Done |
| FR-12 | Main dashboard: KPI tiles, events, check-ins by hour, activity | 2 | `pages/dashboard/index.php` | S03 | Done |
| FR-13 | Automatic day-before reminder (email at SMS) | 3 | `includes/automation.php` (`sendDueReminders`), `cron/notify.php` | I08, S13, M13, UAT08 | Built; **kailangan i-register ang hourly Task Scheduler** |
| FR-14 | Automatic feedback link pagkatapos ng event, at manual button sa event page | 3 | `includes/automation.php` (`sendFeedbackLinks`), `pages/events/view.php`, `pages/feedback/index.php` | U15, I07, M14 | Built; automatic na bahagi kailangan ng Task Scheduler |
| FR-15 | Walang dobleng padala; failed sends inuulit hanggang 3 beses | 3 | `includes/automation.php` (`notYetSent`), `email_queue`, `sms_queue` | I07, I08, M13 | Done |
| FR-16 | Feedback form para sa attendee (event at sessions) | 3 | `pages/feedback/form.php` | S15, UAT09 | Done |
| FR-17 | Login at logout | Supp | `pages/auth/login.php`, `pages/auth/logout.php`, `core/auth.php` | U09, S01–S03 | Done |
| FR-18 | Registration gamit ang passkey | Supp | `pages/auth/register.php`, `passkeys` table | M01, M02 | Done |
| FR-19 | Role-based access (Super Admin, Admin, Staff) | Supp | `core/auth.php` (`requireRole`, `canCreateRole`, `canEditAttendee`), `includes/sidebar.php` | U11, U12, S04, S05, S12 | Done |
| FR-20 | Account management at passkeys | Supp | `pages/accounts/index.php` | U11, M03 | Done |
| FR-21 | Create, end at archive ng event; naka-lock ang archived | Supp | `pages/events/*`, `api/events/*` | U07, U12, M04, M16 | Done |
| FR-22 | Reports at CSV export | Supp | `pages/reports/index.php`, `api/attendees/export.php` | S05, M15, UAT10 | Done |
| FR-23 | Audit trail ng mga aksyon | Supp | `core/logger.php`, `activity_logs` table | I09 | Done |
| FR-25 | Naka-capital ang unang letra ng bawat salita sa pangalan, company, designation at titles | Supp | `core/helpers.php` (`capitalizeWords`), lahat ng save points, `includes/header.php` (live) | U16 | Done |
| FR-24 | Testing at documentation | 4 | `tests/run_tests.php`, `docs/test-cases.md`, `docs/ERD.md`, `docs/ERD.png`, ang file na ito | 40 automated tests (40/40 Pass), 16 manual, 10 UAT | Automated: Done. Manual at UAT: gagawin pa |

## Non-functional requirements

| Req ID | Requirement | Paano natugunan | Test |
|---|---|---|---|
| NFR-01 | Password security | bcrypt (`password_hash`), hindi naka-save ang plain password | U09 |
| NFR-02 | Protektado ang pages at APIs | Lahat ng page at API ay may `requireLogin()` o `requireRole()` | S01, S04, S12 |
| NFR-03 | Proteksyon laban sa SQL injection at CSRF | Prepared statements; SameSite=Strict session cookie at CSRF tokens sa forms | S12 |
| NFR-04 | Data integrity | Unique keys (email bawat event, attendee code, session attendance) at foreign keys | I02, I06, `ERD.md` |
| NFR-05 | Responsive (laptop at phone) | Design system, sinubukan sa 1366px at 390px | `DESIGN_SYSTEM.md`, UAT09 |
| NFR-06 | Bilis ng check-in | Isang scan, isang confirm | UAT04 |

## Buod bawat objective

| Objective | Requirements | Automated tests | Manual / UAT | Status |
|---|---|---|---|---|
| Obj 1 | FR-01 – FR-06 | 15 | 8 | Done |
| Obj 2 | FR-07 – FR-12 | 10 | 8 | Done |
| Obj 3 | FR-13 – FR-16 | 5 | 4 | Code done at tested; i-register ang hourly task para tunay na automatic |
| Obj 4 | FR-24 | 40 | 26 | Automated done; manual at UAT gagawin pa |
