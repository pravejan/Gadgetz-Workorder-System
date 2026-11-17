# GADGETZ.LK Work Order Management System

A web-based job tracking system for a computer sales and repair shop. It manages the full repair lifecycle, from device intake to customer collection, and prints branded A5 job sheets.

**Stack:** PHP 8 (PDO) · MySQL/MariaDB · HTML/CSS · Vanilla JavaScript

## Features

- **Role-based access:** admin and technician logins
- **Customers:** add, edit and search, with repair history
- **Work orders:** device details, accessories checklist, physical condition, complaint, estimated cost, photo uploads, auto-numbered (`WO-2026-0001`)
- **Workflow:** Received → Diagnosing → Waiting for Parts → Repairing → Completed → Collected
- **Tabs:** Pending / Completed / Collected / All, with search
- **Mark as Completed:** one click, records the completion date and time
- **Technician panel:** diagnosis notes, parts used, actual cost
- **Reports:** status donut chart, top device types, monthly intake, completion rate, average turnaround (server-rendered SVG, no external libraries)
- **Print-ready A5 job sheets:** customer copy and shop copy
- **WhatsApp click-to-chat:** opens the customer's chat with a pre-filled message

## Getting started (XAMPP)

1. Install [XAMPP](https://www.apachefriends.org/) and start **Apache** and **MySQL**.
2. Copy this folder into `C:\xampp\htdocs\` (for example `C:\xampp\htdocs\Workorder`).
3. Open `http://localhost/Workorder/` in your browser.
4. The database `computer_repair_db` and all tables are created automatically on first run.

### Default logins

| Role | Username | Password |
|---|---|---|
| Admin | `admin` | `admin123` |
| Technician | `tech` | `tech123` |

> **Change these passwords before using the system with real data.**

### Configuration

Database credentials and shop details are in `php/config.php`:

- `$host`, `$db_user`, `$db_pass`, `$db_name`: database connection (XAMPP defaults)
- `SHOP_PHONE_DISPLAY`, `SHOP_WEBSITE`, `SHOP_ADDRESS_LINE1/2`: letterhead details on printed sheets

## Printing on A5

The job sheet is designed for A5 paper (148 × 210 mm). When printing to a physical printer, open **More settings** in the print dialog and check that **Paper size = A5** and **Scale = 100%**.

## Project structure

```
├── index.php             redirects to login
├── php/
│   ├── config.php        DB connection, helpers, shop details
│   ├── login.php / logout.php
│   ├── dashboard.php
│   ├── customers.php
│   ├── workorder.php     create, edit, view, list, complete
│   ├── technician.php
│   ├── reports.php
│   └── print.php         customer and shop job sheets
├── css/style.css
├── js/script.js
├── images/               logo and uploads/ (uploaded photos, git-ignored)
└── database/database.sql schema and seed users
```

## Security notes

- Prepared statements (PDO) for all queries
- Output escaping against XSS
- Hashed passwords
- Login and role checks on every page

## Limitations

- Needs a PHP + MySQL host. It cannot run on static-only hosting such as Firebase Hosting.
- WhatsApp sending is manual (click-to-chat). Automatic sending would need the WhatsApp Business API.
- Work orders cannot be deleted.
- Back up `images/uploads/` and the database separately from the code.

## License

MIT, see [LICENSE](LICENSE).
