# Full_Event System v2
**Web-Based Event Check-In with QR Code & Real-Time Monitoring**
*Full Circle Events Asia, Inc.*

---

## QUICK START (5 MINUTES)

### **1. Setup**
1. Get the code into `C:\xampp\htdocs\Full_Event` (the folder name must be `Full_Event`):
   ```
   git clone https://github.com/HeyYunixsu/Full-Circle.git C:\xampp\htdocs\Full_Event
   ```
   (Or on GitHub: Code → Download ZIP, extract, and rename the folder to `Full_Event`.)
2. Copy `config\config.example.php` to `config\config.php`. It works as-is for testing; add the Gmail and Semaphore details only if you need real emails or texts.
3. In XAMPP Control Panel → Apache → Config → `php.ini`, find `;extension=gd` and remove the `;` (needed for QR images). Save.
4. Start XAMPP (Apache + MySQL)
5. Open: `http://localhost/phpmyadmin` → Import → choose `database/full_event_db.sql` → Go
6. Open: `http://localhost/Full_Event/`

### **2. Login**
- Email: `superadmin@fullcircle.com`
- Password: `admin123`

### **3. Try the Full Flow!**
1. Click "Events" → "Add New Event"
2. Create an event
3. Click "Upload Attendees" → use sample CSV
4. Click "Attendees" → see all imported attendees with QR codes
5. Click "View QR" beside any attendee
6. Click "Check-In" → "Scan QR Code"
7. Show the QR on another phone → it auto-scans!
8. Badge appears → click "Print Badge"

---

## COMPLETE FEATURES

- Login & Registration (with passkey)
- Role-based access (Super Admin / Admin / Staff)
- Main Dashboard with stats
- Create / View Events
- Companies management per event
- Upload Excel/CSV attendees
- Auto-generate unique QR per attendee
- Attendee list (search, filter, status)
- QR Code Scanner (browser camera)
- Auto-generated Badge Preview
- Print Badge
- Walk-in Registration
- Real-time Check-in Dashboard
- Per-company stats
- Export attendees to CSV
- Email queue (ready for SMTP)

---

## TEST ACCOUNTS (all password: `admin123`)

| Role | Email |
|------|-------|
| Super Admin | `superadmin@fullcircle.com` |
| Admin | `admin@fullcircle.com` |
| Staff | `staff@fullcircle.com` |

## PASSKEYS

- `STAFF2026` — staff role
- `ADMIN2026` — admin role
- `SUPER2026` — super admin role

---

## CSV FORMAT

| name | email | company | mobile | designation |
|------|-------|---------|--------|-------------|
| Juan Cruz | juan@sap.com | SAP | 09171234567 | IT Manager |
| Maria Santos | maria@delmonte.com | Delmonte | 09187654321 | HR Head |

`mobile` and `designation` are optional. Mobile accepts 09XX, +639XX, or spaced formats; invalid numbers are left blank.

Download sample CSV inside Upload Attendees page.

---

## FULL FLOW

```
Admin uploads Excel → System generates QR per attendee
↓
Admin sends QR via email (queued)
↓
Event Day: Attendee shows QR → Staff scans
↓
System detects → Badge appears → Print
↓
Dashboard updates real-time
```

---

## TROUBLESHOOTING

**Login "Invalid email or password":**
Re-import `database/full_event_db.sql` (fresh password hashes)

**CSV upload fails:**
Save Excel file as `.csv` first

**QR Scanner not working:**
Allow camera permission, use Chrome/Firefox

---

## CREDITS

**Capstone Team — STI College Global City**
- Rosemarie G. Caperiña
- Joel Q. Durante Jr.
- Anthony Carl A. Emblan
- Princess Jean A. Ogario

*Built with *
