# Cron — automated notifications

Ito ang sagot sa "automated" ng Objective 3. Kung manual button lang
lahat, hindi automated — tatanungin ka niyan sa defense.

## Ano ang ginagawa

`notify.php` — patakbuhin bawat oras. Logic nasa `includes/automation.php`.

| Kailan | Ano | Kanino |
|---|---|---|
| 1 araw bago ang event (H-1) | Reminder email + SMS | Lahat ng attendee ng event bukas |
| Pagkatapos ng event | Feedback link email | Mga naka-check-in lang |

- "Pagkatapos ng event" = naka-mark na Completed (End Event button), o lumipas na ang petsa.
  Hourly ang takbo, kaya within 1 hour pagkatapos mag-End Event.
- Events lang sa nakaraang 7 araw ang pinapadalhan ng feedback link.
- Hindi nagdodoble: naka-log lahat sa `email_queue` / `sms_queue`, at iyon ang chine-check.
- Kapag pumalya (hal. SMTP error), uulitin sa susunod na takbo, hanggang 3 beses.
- SMS: sumusunod sa `SMS_ENABLED` / `SMS_MOCK` sa config.php.
- May "Send feedback links now" button din sa Feedback page (para sa demo).

Note: H-3 email sa dating plano. Ginawang H-1 dahil "tomorrow" ang wording
ng reminder template. Palitan ang `'+1 day'` sa `sendDueReminders()` kung gusto.

## Subukan nang mano-mano

    C:\xampp\php\php.exe C:\xampp\htdocs\Full_Event\cron\notify.php

Output: `2026-10-05 11:36:12  reminder email 0 sent/0 failed, ...`

## Setup sa Windows (XAMPP)

Isang command lang (cmd, hindi kailangan ng admin):

    schtasks /Create /TN "Full_Event Notify" /SC HOURLY /TR "C:\xampp\php\php.exe C:\xampp\htdocs\Full_Event\cron\notify.php"

O sa Task Scheduler -> Create Basic Task -> Daily, tapos sa Triggers -> Edit ->
"Repeat task every: 1 hour" for "Indefinitely".
  Program:   C:\xampp\php\php.exe
  Arguments: C:\xampp\htdocs\Full_Event\cron\notify.php

Tanggalin: `schtasks /Delete /TN "Full_Event Notify" /F`

Kailangang naka-on ang MySQL (XAMPP) para gumana.
Naka-block ang folder na ito sa browser (.htaccess); CLI lang.

Links sa email (feedback form, QR) ay galing sa `BASE_URL` sa config.php.
Habang `http://localhost/...` pa ito, sa computer lang na ito bubukas ang link.
Palitan kapag naka-deploy na.
