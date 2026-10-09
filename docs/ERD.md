# Entity Relationship Diagram (ERD)

Full Circle Events Asia, Inc. : Event Check-in System. Database: `full_event_db` (MariaDB 10.4), 13 tables, 17 foreign keys.

Kinuha ito diretso sa live database (information_schema), kaya tugma sa code. Ang larawan para sa paper ay `ERD.png` sa folder na ito. Kapag nagbago ang schema (bagong migration), i-update ang diagram na ito at ang `ERD.png`.

Basa ng mga simbolo: `||` eksaktong isa, `|o` zero o isa, `o{` zero o marami. PK = primary key, FK = foreign key, UK = unique.

```mermaid
erDiagram
    users ||--o{ events : "gumawa (created_by)"
    users |o--o{ activity_logs : "gumawa ng aksyon"
    users |o--o{ passkeys : "gumawa / gumamit"
    users |o--o{ session_attendance : "nag-scan (scanned_by)"
    events ||--o{ attendees : "may registered"
    events ||--o{ companies : "may kasaling company"
    events ||--o{ sessions : "may sessions"
    events |o--o{ badge_templates : "badge design"
    events ||--o{ feedback : "may feedback"
    attendees ||--o{ feedback : "nagbigay"
    sessions |o--o{ feedback : "para sa session (optional)"
    sessions ||--o{ session_attendance : "may dumalo"
    attendees ||--o{ session_attendance : "dumalo sa session"
    attendees ||--o{ email_queue : "pinadalhan ng email"
    attendees ||--o{ sms_queue : "pinadalhan ng SMS"
    attendees ||--o{ email_archive : "naka-archive na mensahe"

    activity_logs {
        int id PK
        int user_id FK
        varchar action
        text details
        varchar ip_address
        timestamp created_at
    }
    attendees {
        int id PK
        int event_id FK
        varchar attendee_code UK
        varchar full_name
        varchar email
        varchar mobile_number
        varchar company
        varchar designation
        varchar qr_code
        varchar qr_image_path
        enum registration_type
        enum status
        datetime check_in_time
        tinyint badge_printed
        tinyint email_sent
        tinyint sms_sent
        tinyint reminder_sent
        int name_edited_by
        datetime name_edited_at
        text notes
        timestamp created_at
        timestamp updated_at
    }
    badge_templates {
        int id PK
        int event_id FK
        varchar template_name
        longtext layout_json
        varchar background_color
        varchar accent_color
        varchar text_color
        enum badge_size
        tinyint show_company
        tinyint show_qr
        varchar logo_path
        tinyint is_default
        tinyint is_active
        tinyint is_favorite
        int created_by
        timestamp created_at
    }
    companies {
        int id PK
        int event_id FK
        varchar company_name
        int max_attendees
        varchar company_logo
        timestamp created_at
    }
    email_archive {
        int id PK
        int attendee_id FK
        varchar recipient_email
        varchar recipient_mobile
        varchar subject
        text message
        enum email_type
        enum sent_via
        timestamp sent_at
        enum status
    }
    email_queue {
        int id PK
        int attendee_id FK
        enum email_type
        varchar recipient_email
        varchar subject
        text body
        enum status
        datetime sent_at
        text error_message
        timestamp created_at
    }
    events {
        int id PK
        varchar event_name
        date event_date
        time event_time
        varchar location
        enum event_type
        text description
        varchar event_image
        varchar event_manager
        int max_attendees
        int badge_template_id FK
        varchar registration_qr
        enum status
        datetime archived_at
        int archived_by
        int created_by FK
        timestamp created_at
        timestamp updated_at
    }
    feedback {
        int id PK
        int event_id FK
        int session_id FK
        int attendee_id FK
        int rating
        text comment
        timestamp submitted_at
    }
    passkeys {
        int id PK
        varchar passkey_code UK
        enum role
        tinyint is_used
        int created_by FK
        int used_by FK
        timestamp created_at
        datetime used_at
    }
    sessions {
        int id PK
        int event_id FK
        varchar session_name
        varchar session_type
        varchar speaker_name
        varchar speaker_role
        varchar speaker_photo
        date session_date
        time start_time
        time end_time
        varchar location
        text description
        timestamp created_at
    }
    session_attendance {
        int id PK
        int session_id FK
        int attendee_id FK
        datetime scanned_at
        int scanned_by FK
    }
    sms_queue {
        int id PK
        int attendee_id FK
        enum sms_type
        varchar recipient_number
        text message
        enum status
        varchar provider_ref
        int credits_used
        datetime sent_at
        text error_message
        timestamp created_at
    }
    users {
        int id PK
        varchar first_name
        varchar middle_name
        varchar last_name
        varchar email UK
        varchar password
        enum role
        enum status
        varchar profile_picture
        datetime last_login
        timestamp created_at
        timestamp updated_at
    }
```

## Relationships

| Parent | Child | FK column | Cardinality | Kapag binura ang parent |
|---|---|---|---|---|
| users | events | `created_by` | 1 : N | SET NULL (maiiwan ang event) |
| users | activity_logs | `user_id` | 1 : N | SET NULL (maiiwan ang log) |
| users | passkeys | `created_by`, `used_by` | 1 : N | SET NULL |
| users | session_attendance | `scanned_by` | 1 : N | SET NULL |
| events | attendees | `event_id` | 1 : N | CASCADE |
| events | companies | `event_id` | 1 : N | CASCADE |
| events | sessions | `event_id` | 1 : N | CASCADE |
| events | badge_templates | `event_id` (nullable: global template kung NULL) | 0..1 : N | CASCADE |
| badge_templates | events | `events.badge_template_id`: design na ipi-print sa badges ng event (NULL = favorite design) | 0..1 : N | SET NULL |
| events | feedback | `event_id` | 1 : N | CASCADE |
| attendees | feedback | `attendee_id` | 1 : N | CASCADE |
| sessions | feedback | `session_id` (NULL = feedback sa buong event) | 0..1 : N | CASCADE |
| sessions | session_attendance | `session_id` | 1 : N | CASCADE |
| attendees | session_attendance | `attendee_id` | 1 : N | CASCADE |
| attendees | email_queue | `attendee_id` | 1 : N | CASCADE |
| attendees | sms_queue | `attendee_id` | 1 : N | CASCADE |
| attendees | email_archive | `attendee_id` | 1 : N | CASCADE |

`session_attendance` ay ang associative table ng many-to-many na **attendees ↔ sessions** (isang attendee, maraming session; isang session, maraming attendee).

## Data dictionary (buod)

| Table | Laman | Mahalagang rules |
|---|---|---|
| users | Accounts ng Super Admin, Admin at Staff | `email` unique; password naka-bcrypt; 5 maling password -> `locked_until` = 15 minuto (`failed_logins` ang bilang) |
| passkeys | Codes para makapag-register ng bagong account at role | `passkey_code` unique; `is_used` para isang beses lang magamit |
| events | Events: petsa, oras, lugar, status, badge design | status: upcoming → ongoing → completed → archived; `badge_template_id` NULL = favorite design ang gamit |
| companies | Mga company na kasali sa event at ang attendee limit nila | `max_attendees` NULL = walang limit |
| attendees | Registered at walk-in na attendees, QR code, check-in status | `attendee_code` unique; isang email lang bawat event (`event_id` + `email`) |
| sessions | Talks o breakouts sa loob ng event | |
| session_attendance | Sino ang na-scan sa bawat session | isang record lang bawat attendee bawat session |
| feedback | Rating (1–5) at comment ng attendee | isang feedback bawat attendee bawat session |
| email_queue | Log ng bawat email (QR, reminder, feedback): sent o failed | ginagamit ng automation para hindi magpadala ng doble |
| sms_queue | Log ng bawat SMS, kasama ang Semaphore reference at credits | ginagamit ng automation para hindi magpadala ng doble |
| email_archive | Archive ng mga mensahe ng na-archive na event | |
| badge_templates | Mga design mula sa Badge Designer | `layout_json` ang laman ng design |
| activity_logs | Audit trail: login, check-in, imports, account changes | |

## Mga link na hindi naka-foreign key

Ang mga ito ay tumuturo sa ibang table pero walang FK constraint sa database (by design o legacy):

- `attendees.company` ↔ `companies.company_name` (tugma sa pangalan, hindi sa id; para gumana rin ang company na tina-type lang sa CSV)
- `attendees.name_edited_by`, `events.archived_by`, `badge_templates.created_by` → `users.id`
