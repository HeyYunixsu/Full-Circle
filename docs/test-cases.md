# Test Cases (Objective 4)

Full Circle Events Asia, Inc. : Event Check-in System

Apat na level ng testing:

| Level | Ilan | Paano | Resulta |
|---|---|---|---|
| Unit | 16 | Automated, `tests/run_tests.php` | 16/16 Pass (2026-10-07) |
| Integration | 9 | Automated, `tests/run_tests.php` | 9/9 Pass (2026-10-06) |
| System (automated) | 15 | Automated sa web server (HTTP), `tests/run_tests.php` | 15/15 Pass (2026-10-06) |
| System (manual) | 16 | Manual sa browser at phone | Punan habang tini-test |
| UAT | 10 | Mga user ng Full Circle Events Asia | Punan sa UAT session |

Saan naka-link ang bawat test sa objectives: `objectives-traceability.md`.

## Paano patakbuhin ang automated tests

1. Buksan ang XAMPP, i-start ang **Apache** at **MySQL**.
2. Sa Command Prompt:

   ```
   C:\xampp\php\php.exe C:\xampp\htdocs\Full_Event\tests\run_tests.php
   ```

3. Dapat ang huling linya ay `40/40 passed. Test data removed.` Kapag may `FAIL`, nakasulat sa tabi kung bakit.

Gumagawa ang script ng sariling test data (ZZTEST users, events at attendees, `@example.test` emails) at binubura lahat pagkatapos, kahit may pumalya. Walang email o SMS na pinapadala, kaya ligtas itong patakbuhin kahit may totoong data ang database.

Environment ng run noong 2026-10-06: Windows 10, XAMPP (PHP 8.2, MariaDB 10.4), Apache sa `http://localhost/Full_Event`.

## 1. Unit tests

Isang function lang ang tine-test bawat isa, walang database.

| ID | Function | Input | Expected result | Actual | Status |
|---|---|---|---|---|---|
| U01 | `normalizePHMobile()` | `09171234567` | `639171234567` | `639171234567` | Pass |
| U02 | `normalizePHMobile()` | `+63 917 123 4567` | `639171234567` | `639171234567` | Pass |
| U03 | `normalizePHMobile()` | `9171234567` | `639171234567` | `639171234567` | Pass |
| U04 | `normalizePHMobile()` | `12345`, blank | `false` (invalid) | `false` | Pass |
| U05 | `formatPHMobileDisplay()` | `639171234567` | `0917 123 4567` | `0917 123 4567` | Pass |
| U06 | `smsCreditCost()` | 160 at 161 na characters | 1 credit; 2 credits | 1; 2 | Pass |
| U07 | `isValidEventDate()` | 2026-12-01; 1999-01-01; 2101-01-01; `abc` | valid; invalid; invalid; invalid | gaya ng expected | Pass |
| U08 | `generateQRData()` | code `123456789` | Format `FC-<HEX>-123456789`; iba ang value bawat tawag | gaya ng expected | Pass |
| U09 | `hashPassword()` / `verifyPassword()` | `secret123`, `wrong` | Hindi plain text ang hash; tama lang ang tinatanggap | gaya ng expected | Pass |
| U10 | `capacityBadge()` | 100/100; 105/100; 90/100; 50/100; walang limit | Full; Over by 5; Almost full; wala; wala | gaya ng expected | Pass |
| U11 | `canCreateRole()` | role ng naka-login vs role na gagawin | Super Admin → Admin, Staff; Admin → Staff lang; Staff → wala | gaya ng expected | Pass |
| U12 | `canEditAttendee()` | role + event status | Archived: walang pwedeng mag-edit; Staff: ongoing lang | gaya ng expected | Pass |
| U13 | `parseAttendeesCSV()` | CSV na `;` ang separator, may header, may duplicate email | Nade-detect ang `;`, 2 rows (tinanggal ang duplicate), tamang company | gaya ng expected | Pass |
| U14 | `parseAttendeesCSV()` | CSV na walang email column | `success = false` at may error message | gaya ng expected | Pass |
| U15 | `feedbackLink()` | QR value `FC-A B` | `BASE_URL/pages/feedback/form.php?c=FC-A+B` | gaya ng expected | Pass |
| U16 | `capitalizeWords()` | ` juan dela cruz `; `SAP philippines`; `IT`; `mary-ann` | `Juan Dela Cruz`; `SAP Philippines`; `IT`; `Mary-Ann` | gaya ng expected | Pass |

## 2. Integration tests

PHP code kasama ang database.

| ID | Ano ang tine-test | Steps | Expected result | Actual | Status |
|---|---|---|---|---|---|
| I01 | Unique attendee code | Tawagin ang `generateAttendeeCode()` | 9 digits, wala pang kaparehas sa `attendees` | gaya ng expected | Pass |
| I02 | Duplicate email sa iisang event | Mag-insert ng attendee na may email na naka-register na sa event | Tinanggihan ng database (error 1062, `uniq_event_email`) | error 1062 | Pass |
| I03 | Parehong email, ibang event | Mag-insert ng parehong email sa ibang event | Tinanggap | tinanggap | Pass |
| I04 | Event statistics | 2 attendees (1 checked in), `getEventStats()` | total 2, checked in 1, not yet 1, 50% | gaya ng expected | Pass |
| I05 | Company limit counter | Company na may limit 2 at 2 attendees, `companyCapacity()` | 2 registered / limit 2, badge "Full" | gaya ng expected | Pass |
| I06 | Session attendance | I-scan ang parehong attendee nang 2 beses sa isang session | Unang scan nare-record; pangalawa hindi na nadodoble; 1 lang sa listahan | gaya ng expected | Pass |
| I07 | Automation: walang dobleng feedback email | Checked-in attendee → mag-log ng "sent" na feedback email | Bago: kasama sa padadalhan. Pagkatapos: hindi na | gaya ng expected | Pass |
| I08 | Automation: retry limit | Mag-log ng 2, tapos 3 failed na reminder SMS | Sa 2 failures, susubukan pa; sa 3, titigil na | gaya ng expected | Pass |
| I09 | Audit trail | Tawagin ang `logActivity()` | May bagong row sa `activity_logs` | may row | Pass |

## 3. System tests (automated, through the web server)

Totoong HTTP requests sa Apache, gaya ng browser. Gumagamit ng ZZTEST Staff at ZZTEST Admin accounts.

| ID | Scenario | Steps | Expected result | Actual | Status |
|---|---|---|---|---|---|
| S01 | Protected page | Buksan ang Dashboard nang hindi naka-login | Redirect sa Login page | redirect sa login | Pass |
| S02 | Maling login | Login na tama ang email, mali ang password | "Invalid email or password." | gaya ng expected | Pass |
| S03 | Tamang login | Login bilang Staff, buksan ang Dashboard | Dashboard bukas (HTTP 200) | 200 | Pass |
| S04 | Role restriction | Bilang Staff: Reports, Companies, Feedback, Accounts, Create Event | Lahat nire-redirect sa Dashboard | lahat redirect | Pass |
| S05 | Admin access | Bilang Admin: Reports at Companies | Bukas (HTTP 200) | 200 | Pass |
| S06 | QR scan, preview | I-scan ang QR ng attendee na hindi pa naka-check in | Lumabas ang details para i-verify; hindi pa naka-check in sa database | gaya ng expected | Pass |
| S07 | QR scan, confirm | I-confirm ang check-in | "Checked in successfully!"; status `checked_in` at may oras | gaya ng expected | Pass |
| S08 | Double scan | I-scan at i-confirm ulit ang parehong QR | "Already checked in at …"; walang dobleng check-in | gaya ng expected | Pass |
| S09 | Manual entry | I-type ang 9-digit attendee code sa halip na QR | Nahanap ang attendee | nahanap | Pass |
| S10 | Maling event | I-scan ang QR ng attendee ng ibang event | "QR code not recognized. Not registered for this event." | gaya ng expected | Pass |
| S11 | Real-time monitoring | Pagkatapos ng check-in, tawagin ang live update (`api/attendees/poll.php`) | Kasama ang bagong check-in at tama ang count | gaya ng expected | Pass |
| S12 | API security | Check-in request nang hindi naka-login | Redirect sa login; walang nabago sa database | gaya ng expected | Pass |
| S13 | Cron security | Buksan ang `cron/notify.php` sa browser | HTTP 403 "CLI only" (Task Scheduler lang ang pwedeng magpatakbo) | 403 | Pass |
| S14 | Attendee QR page | Buksan ang QR link ng attendee; tapos maling code | Lumabas ang pangalan at QR; maling code → "QR Not Found" | gaya ng expected | Pass |
| S15 | Feedback form | Mag-submit ng rating 0, tapos 5, tapos 4 | 0 → error "rating from 1 to 5"; 5 → saved; 4 → na-update, hindi doble | gaya ng expected | Pass |

## 4. System tests (manual)

Para sa mga bahagi na kailangan ng camera, printer, totoong email o SMS, o pagtingin sa screen. Gamitin ang test event at sariling email o phone number ng team; huwag gamitin ang totoong attendees. Punan ang **Actual** at **Status** habang ginagawa.

| ID | Scenario | Steps | Expected result | Actual | Status |
|---|---|---|---|---|---|
| M01 | Register gamit ang passkey | Register page → punan ang form → passkey `STAFF2026` | "Account created successfully! Please login." Nagamit na ang passkey | | |
| M02 | Gamit na o maling passkey | Mag-register ulit gamit ang parehong passkey | "Invalid or already used passkey. Please contact your Project Manager." | | |
| M03 | Account management | Bilang Admin: gumawa ng Staff account; i-reset ang password nito sa 5 characters | "Account created for …"; "Password must be at least 6 characters." | | |
| M04 | Create event | Add Event → taon 1999 → Save; tapos tamang petsa | "Invalid event date. Year must be between 2000 and 2100."; tapos "Event created successfully!" | | |
| M05 | Company limit | Companies → limit 2 sa isang company → mag-import ng 3 attendees ng company na iyon | Event page: counter "3 / 2" at "Over by 1" | | |
| M06 | Import attendees | Upload Attendees → sample CSV na may 1 duplicate at 1 maling mobile → Import | Preview muna; tapos "Imported N attendees, skipped 1 … 1 invalid mobile number left blank" | | |
| M07 | Send QR invite | Attendees → Send Invite (Email) sa sariling test attendee | Dumating ang email na may QR code at link | | |
| M08 | Camera QR scan | Check-in → Scan QR → ipakita ang QR mula sa phone → Confirm | Lumabas ang attendee card; "Checked in successfully!"; lumabas ang badge | | |
| M09 | Walk-in | Walk-in form: maling mobile; email na naka-register na; tapos tamang data | "Invalid mobile number. Use 09XXXXXXXXX format."; "This email is already registered for this event."; tapos badge page | | |
| M10 | Badge print | Badge page → Print Badge | Print preview: badge lang ang laman, tamang pangalan, company at QR | | |
| M11 | Real-time dashboard | Buksan ang Check-in dashboard sa laptop; mag-check in gamit ang phone | Nag-update ang count at listahan sa loob ng mga 2 segundo, walang refresh | | |
| M12 | Session attendance | Sessions → Add session → Scan for this session → i-scan ang attendee | Lumabas sa Session Attendance list ang attendee | | |
| M13 | Automated reminder | Event bukas, may attendee na test email/phone → patakbuhin ang `cron/notify.php` (2 beses) | Unang run: may reminder email at SMS. Pangalawang run: 0 sent (walang doble) | | |
| M14 | Feedback links | Completed event → "Send feedback links (N)" → Send links | Dumating ang feedback email; naging "Feedback links sent" ang button | | |
| M15 | Reports at export | Reports → pumili ng event → Export CSV | Tama ang KPI tiles; nabubuksan ang CSV sa Excel | | |
| M16 | Archive lock | Archive ang event → subukang mag-edit ng attendee o company | "This event is archived and locked." / read-only | | |

## 5. User Acceptance Testing (UAT)

Gagawin ng mga totoong user (event staff at admin ng Full Circle Events Asia, Inc.) sa isang test event. Ang team ang magbibigay ng test data at magmamasid; ang user ang gagawa ng steps. Isulat ang Pass o Fail at ang comments ng user.

| ID | User | Task | Acceptance criteria | Pass / Fail | Remarks ng user |
|---|---|---|---|---|---|
| UAT01 | Admin | Gumawa ng event at mag-set ng company limits | Nagawa nang walang tulong | | |
| UAT02 | Admin | Mag-import ng attendee list mula sa Excel o CSV | Tama ang bilang at details ng na-import | | |
| UAT03 | Admin | Magpadala ng QR invites | Natanggap ng test attendees ang QR | | |
| UAT04 | Staff | Mag-check in ng 10 attendees gamit ang QR scanner | Bawat check-in ay mga 10 segundo o mas mabilis | | |
| UAT05 | Staff | Mag-register ng walk-in at mag-print ng badge | Tama ang badge, walang nakakalitong step | | |
| UAT06 | Staff | Hanapin ang attendee na nawalan ng QR at i-check in nang manual | Nahanap gamit ang search o attendee code | | |
| UAT07 | Admin | Bantayan ang live dashboard habang may nagche-check in | Nakikita ang updates nang hindi nagre-refresh | | |
| UAT08 | Attendee | Tanggapin ang reminder bago ang event | Dumating ang email at SMS isang araw bago | | |
| UAT09 | Attendee | Magbigay ng feedback gamit ang link sa phone | Madaling gamitin sa phone; na-save ang rating | | |
| UAT10 | Admin | Tingnan ang Reports at i-export | Tugma ang numbers sa nangyari sa event | | |

Sign-off:

| Pangalan | Position | Petsa | Pirma |
|---|---|---|---|
| | | | |
| | | | |
