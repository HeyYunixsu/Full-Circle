# Full_Event v3 — restructured

Ito na ang gagamitin mo. Naka-ayos na ang file structure at
naka-test lahat sa totoong PHP 8.3 + MariaDB 10.11.

## UNAHIN MO ITO

config/config.php ay may totoong Gmail app password. Nakita ko sa zip
na binigay mo. Palitan sa Google Account -> App Passwords, revoke luma.

## Setup

1. Backup ng database mo
2. Palitan ang buong Full_Event folder sa htdocs
3. phpMyAdmin -> SQL tab -> paste ang database/migrations/migration_v3.sql
   (o buksan http://localhost/Full_Event/database/migrations/migrate_v3.php)
4. Optional: SEMAPHORE_API_KEY sa config.php + SMS_ENABLED = true

Kung hindi mo i-on ang SMS, gumagana pa rin lahat.

## BAGONG STRUCTURE

core/                bagong bootstrap layer
  bootstrap.php      isang require lang kailangan ng bawat page
  database.php       nasa config.php pa (hindi pa hiwalay)
  auth.php           login state, roles, canEditAttendee()
  helpers.php        sanitize, format, codes, isValidEventDate()
  flash.php          redirect + flash messages
  logger.php         logActivity()
  stats.php          getEventStats(), getActiveEvent()

api/                 naka-folder na per module
  events/            archive.php, end.php, update_status.php
  attendees/         export.php, poll.php, sample_csv.php, update.php
  checkin/           checkin.php, scan.php
  notifications/     send_email.php, send_sms.php, process_queue.php
  accounts/ sessions/ feedback/ badges/    (bakante pa)

pages/               dagdag na folder: accounts/, companies/, badges/
cron/                para sa automated reminders (Obj 3) - bakante pa
docs/                para sa capstone paper - bakante pa
assets/logos/        para sa company logos

includes/functions.php ay shim na lang -> core/bootstrap.php (now in _unused/; everything requires core/bootstrap.php)
Gumagana pa rin ang lumang code na nag-require nito.

## MGA NA-TEST (totoong runtime, hindi lint lang)

Nag-install ako ng PHP 8.3 + MariaDB 10.11, ginawa kong replica ang
live DB mo, tapos pinatakbo ang sistema.

  53 PHP files                   lint clean
  95 require paths               walang sira
  11 API links                   may target lahat
  lahat ng page BAGO mag-migrate walang error (guarded ang sms_sent)
  lahat ng page PAGKATAPOS       walang error
  migration 2x sunod-sunod       idempotent, walang nawalang data
  walk-in "0917 123 4567"        na-save as 09171234567
  search "Amazon"                nahanap (dating hindi)
  edit attendee                  gumana, may audit log
  invalid mobile                 tinanggihan
  duplicate email                tinanggihan
  archive                        may archived_at + archived_by
  restore via URL                NA-BLOCK
  archive 2x                     NA-BLOCK
  edit sa archived event         NA-BLOCK
  walk-in sa archived event      NA-BLOCK
  Restore button                 wala na, "Locked" na
  PH mobile normalizer           13/13 test cases

## ANG NAAYOS

1. SEARCH SA COMPANY
   Hindi kasama ang company sa search dati. Kaya walang lumalabas
   pag hinanap mo ang company name. Ayos na, may idx_company index.

2. PERMANENT ANG ARCHIVE
   Ang sanhi ay ang "Restore" button sa events/view.php.
   Nasa activity log mo ang ebidensya:
     #28  'EVENT NI EMBLAN' archived -> upcoming
     #49  'SAP EVENT'       archived -> completed
   Tinanggal, plus server-side guards sa tatlong API.

3. WALK-IN FULL DETAILS
   Company, Full Name, Designation, Email, Mobile. May PH validation.

4. EDIT ATTENDEE KAHIT KAILAN
   May pencil button na sa bawat row.

5. SMS (Semaphore)
   Php 0.56/text ex-VAT. Globe, Smart, Sun, DITO.

## HINDI PA GAWA

- CSV import ng mobile + designation
  MAHALAGA: walang mobile ang pre-registered mong attendees kaya
  walang matatext sa bulk SMS hangga't hindi ito nagagawa.

- Sessions, feedback, reports, accounts page, cron, badge editor.
  Nasa Full_Event_v3_Structure.md ang plano.

## KILALANG BUG NA HINDI KO PA GINALAW

- Walang date validation sa events/create.php (may event kang taong 1212)
  May isValidEventDate() na sa core/helpers.php pero hindi pa nakakabit.
- qr_image_path ay absolute Windows path - masisira pag lumipat ng PC
- Walang UNIQUE (event_id, email) - PHP lang ang dedupe
- companies table hiwalay sa attendees.company, walang FK
- api/events/archive.php ay ?id= pero api/events/end.php ay ?event_id=
  Hindi pantay. Gumagana pero nakakalito.
