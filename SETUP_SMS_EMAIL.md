# Setup — Email at SMS

## EMAIL (Gmail muna)

Sa config/config.php, hanapin ang EMAIL PROVIDER section:

  define('EMAIL_PROVIDER', 'gmail');

Sa loob ng gmail block, punan:
  SMTP_USERNAME   = Gmail address mo
  SMTP_PASSWORD   = 16-char App Password (Google Account -> App Passwords)
                    HINDI ang tunay na password mo
  SMTP_FROM_EMAIL = kapareho ng username

Limit: ~100 email/araw. Sapat sa testing at maliliit na event.

### Paglipat sa Brevo (300/araw) mamaya
1. Register: https://www.brevo.com
2. Verify sender: https://app.brevo.com/senders/list
3. SMTP key: https://app.brevo.com/settings/keys/smtp
4. Sa config: palitan 'gmail' -> 'brevo', punan ang brevo block
   (username = login email, password = SMTP key)
Walang ibang code na babaguhin.


## SMS (Semaphore — semaphore.co)

Sa config/config.php, SMS section:

  define('SMS_ENABLED', true);
  define('SMS_MOCK', true);              <- SIMULATION muna, walang gastos
  define('SEMAPHORE_API_KEY', '');       <- ilagay ang API key mo dito
  define('SEMAPHORE_SENDER_NAME', '');   <- blank = default ng account mo

### MOCK MODE (SMS_MOCK = true)
Gumagana ang buong flow pero WALANG totoong SMS na ipinapadala.
Nilo-log sa sms_queue, binibilang ang credits, ipinapakita ang message.
Libre. Ganito muna habang natututo at nagte-test.

### TOTOONG SMS (SMS_MOCK = false)
1. Mag-register sa https://semaphore.co at mag-load ng credits
2. Kunin ang API key sa dashboard (Account -> API)
3. Ilagay sa SEMAPHORE_API_KEY
4. SMS_MOCK = false
5. Mag-test sa SARILING number mo muna

1 credit = 1 SMS na hanggang 160 characters. Mas mahaba = hinahati (2+ credits).
Ang sms_queue.provider_ref ay ang message_id ng Semaphore (para ma-trace sa dashboard nila).

### Sender name
Lalabas sa phone ang sender name ng account mo. Para sa sarili
(hal. FULLCIRCLE), i-register sa Semaphore dashboard -> Sender Names
(may approval), tapos ilagay sa SEMAPHORE_SENDER_NAME.
Kapag maling sender name ang nilagay, error ang ibabalik ng Semaphore.
WALA PANG APPROVED SENDER NAME = ERROR. Ayon sa docs ng Semaphore, kapag
walang registered sender name at walang nilagay, error ang ibabalik at
hindi maipapadala ang SMS. Hintayin ang approval bago mag-live test.

### MAHALAGA
- Huwag simulan ang message sa salitang "TEST" — tahimik itong
  binabalewala ng Semaphore. Hinaharang na ito ng sms.php.
- PH numbers lang. Tinatanggap ang 09XX / 639XX (normalizePHMobile).
- Limit: 120 send requests kada minuto.

### Endpoint
Naka-set na sa SEMAPHORE_API_BASE:
  https://api.semaphore.co/api/v4
Kung mag-iba ang Semaphore, dito mo lang papalitan.


## PAANO GUMAGANA ANG QR LINK

SMS at email ay may link papuntang:
  BASE_URL/pages/qr/view.php?code=XXXXXXXXX

Bubukas ito ng public page (walang login) na may QR code nila.
Ipapakita nila sa staff sa entrance para i-scan.

MAHALAGA: kapag localhost ang BASE_URL, ang link ay HINDI gagana
sa telepono ng ibang tao. Para sa totoong SMS test:
  - Demo: gumamit ng ngrok (bibigyan ka ng totoong https address)
  - Production: i-host sa InfinityFree, tapos palitan ang BASE_URL


## BAGONG FILES

- pages/qr/view.php              public QR page (bubukas ng SMS link)
- includes/sms.php              Semaphore gateway + mock mode

## BINAGO

- config/config.php             email provider switch + Semaphore config
