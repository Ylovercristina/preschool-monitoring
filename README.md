# Preschool Monitoring System (LittleSteps)

A comprehensive, modern, multi-role web-based **Preschool Monitoring System** built in **PHP** with modern HTML5, Vanilla CSS design system, and SQLite/MySQL PDO database backend.

## 👥 Project Team & Authors
1. **Rhysa A. Caruz**
2. **Cristine Joy B. Jaojao**
3. **Xyrha Viel Sacal**

---

## 🚀 Quick Start (Instant Run)

### Method 1: One-Click Windows Launcher
Double-click `run.bat` located in the root directory. It will automatically launch the built-in PHP server and open `http://localhost:8000` in your web browser.

### Method 2: Command Line
Open PowerShell or Command Prompt in this folder and run:
```bash
php -S localhost:8000
```
*(Or use `C:\Users\LENOVO\php\php.exe -S localhost:8000`)*

Then open your browser to: **`http://localhost:8000`**

> **Zero-Configuration Database:** The system includes automatic SQLite migration and rich initial demo seeding upon first launch. No manual database creation or SQL imports required!

---

## 🔑 Pre-Configured Demo Accounts

For rapid evaluation, the login page includes **1-Click Quick Demo Login Buttons**:

| Role | Email | Password | Linked Demo Context |
|---|---|---|---|
| **Admin** | `admin@preschool.com` | `admin123` | Full administrative control, fee tracker, parent approvals, system logs & reports |
| **Teacher** | `teacher@preschool.com` | `teacher123` | Teacher Sarah Jenkins (Pre-K - Little Explorers), daily attendance, milestone evaluator, pickup verifier |
| **Parent (Active)** | `parent@preschool.com` | `parent123` | Parent Emily Watson (Child: Leo Watson), attendance viewer, milestone card, pickup pass, fee statement |
| **Parent (Pending)** | `clara@preschool.com` | `parent123` | Parent Clara Reyes (Awaiting Admin review in Parent Approvals center) |

---

## 📋 Requirements Traceability Matrix

This application implements **100%** of the functional requirements and user stories from the specification document:

### 1. Admin Requirements
| Feature | User Story / Description | System Implementation |
|---|---|---|
| **Signup** | Create an account to access the system | `auth/signup.php` (Admin role selector) |
| **Login** | Login to access system features | `auth/login.php` (Secure password hashing & session management) |
| **Logout** | Logout to prevent unauthorized access | `auth/logout.php` (Session termination) |
| **Forgot password** | Reset account to regain access | `auth/forgot-password.php` (Password recovery flow) |
| **Monitor student academic progress** | Monitor and review students' academic progress, evaluate performance & identify students who may need support | `admin/progress.php` (Domain breakdown, early intervention radar & milestone drilldowns) |
| **Admission Management** | Record and keep students and teachers files | `admin/students.php` & `admin/teachers.php` (Comprehensive student & faculty files) |
| **Event/Activity Management** | Manage school events and activities, keep track of upcoming activities | `admin/events.php` (Event scheduling, categories, venues & parent notifications) |
| **Add, Archive, Edit** | Add, archive, and edit users information | `admin/users.php` (Create, edit, soft-archive and restore user accounts) |
| **Fee Management** | Track school's fee records, monitor payments and outstanding fees | `admin/fees.php` (Fee categories, student accounts, outstanding balance tracker) |
| **Log payment** | Track and record payments | `admin/fees.php` (Log payment modal, transaction modes, auto-receipt generator) |
| **Manage parent & teacher accounts, system logs, reports** | Manage parent and teacher accounts, system activity logs, report generation (students, fee) | `admin/users.php`, `admin/logs.php` (Audit trail), `admin/reports.php` (CSV export & print views) |
| **Approve parent accounts** | View and manage approval for user | `admin/approvals.php` (Dedicated verification queue to approve or decline parent signups) |

### 2. Teacher Requirements
| Feature | User Story / Description | System Implementation |
|---|---|---|
| **Login / Logout / Forgot Password** | Access and authenticate | `auth/login.php`, `auth/logout.php`, `auth/forgot-password.php` |
| **Update student academic progress** | Record and update students milestones, activities, and assessments to monitor progress | `teacher/progress.php` (Cognitive, Social, Motor, Language, Arts domains & term assessment) |
| **Parent-Teacher Communication** | Send messages to parents to communicate about their child | `teacher/messages.php` (Two-way communication thread per pupil) |
| **Attendance Tracking** | Record student attendance to keep track of present and absent | `teacher/attendance.php` (Daily roster, 1-click status, time-in, remarks & print sheet) |
| **Admission Management** | Review relevant data and manage students enrollment | `teacher/students.php` (Classroom roster, health guidelines, allergy alerts & emergency contacts) |
| **Event/Activity Management** | Notify parents/guardians about what events are happening in school | `teacher/events.php` (Calendar overview & 1-click broadcast notice) |
| **Fee Management** | Notify parents/guardians if school fees need to be paid and send notifications with status and amount | `teacher/reminders.php` (Outstanding fee watchlist & 1-click fee notice dispatcher) |
| **Emergency Alert** | Inform parents/guardians if an emergency is occurring in school | `teacher/emergency.php` (Immediate site-wide broadcast banner & urgent alert notices) |
| **Authorized Pickup Verification** | Verify person picking up student is authorized to release to a trusted person | `teacher/pickups.php` (Authorized guardians roster, PIN code verification & safety release log) |
| **Send reminders to parents** | Send reminders about school fees and upcoming activities | `teacher/reminders.php` (Fee reminders, activity notices & custom classroom broadcasts) |

### 3. Parent Requirements
| Feature | User Story / Description | System Implementation |
|---|---|---|
| **Signup / Login / Logout / Forgot Password** | Parent account lifecycle | `auth/signup.php` (Registers into pending approval queue), `auth/login.php`, `auth/logout.php`, `auth/forgot-password.php` |
| **Monitor student academic progress** | View child's academic progress and performance in school | `parent/progress.php` (Domain competencies, milestone evaluations, teacher remarks & printable progress card) |
| **Parent-Teacher Communication** | Send messages to child's teacher to ask questions and discuss progress | `parent/messages.php` (Direct conversation hub with classroom teacher) |
| **Attendance Tracking** | View child's attendance record to know if child attended school | `parent/attendance.php` (Daily attendance log, time-in, dismissal, excuses & stats) |
| **Receive notifications** | Be notified when there is an event in school | `parent/notifications.php` (Categorized notification feed) |
| **Fee Management** | View child's fees and payment status (paid, balance) | `parent/fees.php` (Itemized invoice statement, balances & printable official receipts) |
| **Emergency Alert** | Receive emergency alerts quickly involving child at school | `parent/notifications.php` & immediate top-level Emergency Banner across the portal |
| **Authorized Pickup Verification** | Register or provide authorized pickup information with photo and PIN code | `parent/pickups.php` (Register guardians, relationship, phone & personal 4-digit security PIN) |
| **Calendar** | School calendar to track school activities of child | `parent/calendar.php` (Interactive monthly calendar of events and theme days) |

---

## 🗄️ Optional MySQL / XAMPP Setup

If you prefer to run the system with **MySQL / MariaDB** via XAMPP:
1. Copy the project into your `C:\xampp\htdocs\collab` folder.
2. Start Apache and MySQL in the XAMPP Control Panel.
3. Open `http://localhost/phpmyadmin` and create a database named `preschool_db`.
4. Import the provided SQL schema file: `database/schema.sql`.
5. Edit `config/database.php` or set environment variables `DB_DRIVER=mysql`, `DB_NAME=preschool_db`, `DB_USER=root`, `DB_PASS=`.

---

## 📂 Project Architecture

```
collab/
├── admin/                 # Administrator Portal (Admissions, Fees, Progress, Approvals, Logs, Reports)
├── teacher/               # Teacher Portal (Daily Attendance, Milestones, Pickup Verifications, Emergency)
├── parent/                # Parent Portal (Child Progress, Attendance History, Pickup Passes, Fees, Calendar)
├── auth/                  # Authentication (Login, Signup, Forgot Password, Logout)
├── assets/
│   ├── css/               # Core design tokens, layout & responsive stylesheets
│   └── js/                # Modal controller, live table filters & interactive calendar
├── config/
│   ├── config.php         # App configuration, security, CSRF & helpers
│   ├── database.php       # PDO connection (SQLite auto-initialization / MySQL)
│   └── migrate_seed.php   # Database tables and rich initial seeder
├── database/
│   ├── preschool.sqlite   # SQLite database file
│   └── schema.sql         # MySQL / MariaDB compatible schema
├── includes/              # Layout includes (header, topbar, sidebar, footer, auth middleware)
├── index.php              # Root gateway & role redirection
├── run.bat                # 1-click Windows execution launcher
└── README.md              # Documentation & manual
```
