# 🎓 Online Exam System

> A modern, secure, **PHP 8 + MySQL** online examination platform — designed to be a great college mini-project and a clean, production-quality codebase to learn from.

[![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-4479A1?logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3?logo=bootstrap&logoColor=white)](https://getbootstrap.com/)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![PRs Welcome](https://img.shields.io/badge/PRs-welcome-brightgreen.svg)](#contributing)

---

## ✨ Features

- 🔐 **Role-based authentication** — separate student and admin flows, bcrypt-hashed passwords, CSRF protection, session regeneration on login.
- ⏱️ **Timed exams** — per-quiz countdown timer with auto-submit, anti-leave warning, and a question palette for jumping around.
- 🧠 **Instant grading & detailed breakdown** — see correct/wrong/skipped answers and the actual correct option per question.
- 🏅 **Achievement certificates** — automatic Silver, Gold, and Platinum certificates, public verification, PDF download, and admin revocation.
- 📊 **Charts & analytics** — score trends, accuracy by topic, attempts-over-time (powered by Chart.js).
- 🏆 **Leaderboard** — global ranking by total marks earned.
- 📄 **PDF / CSV export** — print-ready PDF reports of any attempt + bulk CSV exports for admins.
- 🛠️ **Full admin panel** — CRUD for quizzes/questions, manage students, view all attempts, read feedback, system analytics.
- 🌗 **Dark / light mode** with theme persistence.
- 📱 **Mobile-friendly** Bootstrap 5 UI.
- 🛡️ **Secure by default** — PDO prepared statements everywhere, strict SQL mode, HTML escaping, CSRF tokens on every form.

---

## 🖼️ Screenshots

### Landing page
> Hero section with live exam preview + feature grid.

![Landing page](assets/img/screenshots/landing.png)

### Student dashboard
> At-a-glance KPI cards, score-trend chart (Chart.js), and recent attempts.

![Student dashboard](assets/img/screenshots/dashboard.png)

### Timed exam
> Sticky countdown timer, sticky question palette, and accessible radio options. Auto-submits at zero.

![Timed exam](assets/img/screenshots/exam.png)

### Result page
> Animated score ring, pass/fail grade, per-question breakdown, and PDF / CSV export buttons.

![Result](assets/img/screenshots/result.png)

### Admin dashboard
> System KPIs, 14-day attempts bar chart, and a live feed of recent attempts.

![Admin dashboard](assets/img/screenshots/admin-dashboard.png)

### Admin analytics
> 30-day attempts trend, accuracy doughnut by topic, and a per-quiz summary table.

![Admin analytics](assets/img/screenshots/admin-analytics.png)

---

## 🚀 Quick start (XAMPP / WAMP / MAMP)

This project uses Composer for server-generated certificate PDFs; no Node or frontend build step is required.

### 1. Clone into your web root

```bash
git clone https://github.com/aashishbharti04/online-exam-system.git
# Move it into your XAMPP htdocs (Windows example):
mv online-exam-system "/c/xampp/htdocs/online-exam-system"
```

### 2. Configure the database connection

Open [`config/config.php`](config/config.php) and verify the values match your local MySQL:

```php
const DB_HOST = '127.0.0.1';
const DB_PORT = 3306;
const DB_NAME = 'online_exam_system';
const DB_USER = 'root';
const DB_PASS = '';        // XAMPP default
```

> Prefer to keep secrets out of git? Create `config/config.local.php` with your overrides — it's gitignored.

### 3. Run the one-time installer

From the project directory, install PHP dependencies (including Dompdf for certificate PDFs):

```bash
composer install
```

If Composer is not on `PATH`, run its PHAR using XAMPP's PHP executable, for example:

```powershell
C:\xampp\php\php.exe C:\path\to\composer.phar install
```

Start Apache + MySQL in XAMPP, then in your browser:

```
http://localhost/online-exam-system/install.php
```

The installer will:

1. Create the `online_exam_system` database
2. Import all tables + 25 quizzes (215 questions)
3. Create your **admin account** with a bcrypt-hashed password
4. Optionally create a demo student (`student@oes.local` / `student@123`)

When you're done, **delete `install.php`** from the server.

### Add the expanded course catalog to an existing installation

The catalog update is additive and idempotent. It adds `difficulty` columns to
`quizzes` and `questions` when missing, then inserts the 20 new published courses
and their 10 four-option questions each. It does not drop existing tables, change
the original five courses, or remove exam attempts.

From PowerShell, run the migration with XAMPP's PHP:

```powershell
C:\xampp\php\php.exe C:\xampp\htdocs\online-exam-system\database\migrations\20261030_expand_course_catalog.php
```

Verify the seeded records and answer options:

```powershell
C:\xampp\php\php.exe C:\xampp\htdocs\online-exam-system\tests\course-catalog.php
```

The Browse Exams page lists published courses with search, category and difficulty
filters, name/newest sorting, and pagination. Admins can manage course and question
difficulty in the existing Quizzes and Questions pages. A quiz with saved attempts
is unpublished rather than permanently deleted so result and certificate history
remains available.

### Add course thumbnails to an existing installation

Back up the database, then run the thumbnail migration from PowerShell:

```powershell
C:\xampp\php\php.exe C:\xampp\htdocs\online-exam-system\database\migrations\20261030_add_course_thumbnails.php
```

The migration verifies the expected course names at their existing IDs (and
uses an exact, unique course-title match if IDs differ in another installation),
adds the nullable `quizzes.image_path` column if needed, and assigns each
existing course a unique local 16:9 photograph. The photos are downloaded from
Unsplash and used under the [Unsplash License](https://unsplash.com/license);
source IDs are listed in `assets/images/courses/ATTRIBUTION.txt`. It is safe to
rerun and does not create, delete, or modify exam questions. Deploy the
migration and `assets\images\courses` folder with the application files. The
admin quiz form accepts optional JPEG, PNG, or WebP thumbnails up to 5 MB; a
local photographic default is used when no image is assigned.

Run the thumbnail check with:

```powershell
C:\xampp\php\php.exe C:\xampp\htdocs\online-exam-system\tests\course-thumbnails.php
```

### 4. Sign in

- **Student**: <http://localhost/online-exam-system/login.php>
- **Admin**:   <http://localhost/online-exam-system/admin/login.php>

That's it. 🎉

---

## 🏗️ Project structure

```
online-exam-system/
├── composer.json          Dompdf dependency manifest
├── composer.lock          Locked PHP dependencies
├── index.php              Landing page (with feature tour)
├── install.php            One-time installer (delete after use)
├── login.php              Student sign-in
├── register.php           Student sign-up
├── logout.php
├── dashboard.php          Student dashboard (stats + trend chart)
├── exams.php              Browse / filter available exams
├── take-exam.php          Timed exam UI with question palette
├── submit-exam.php        Grading + persistence
├── result.php             Result page with detailed breakdown
├── certificates.php       Student certificate library
├── certificate.php        Authenticated certificate preview
├── certificate-pdf.php    Verified Dompdf certificate download
├── verify-certificate.php Public certificate verification
├── history.php            All attempts + accuracy chart
├── leaderboard.php        Global top-50 ranking
├── profile.php            Edit profile + change password
├── feedback.php           Submit feedback / bug report
├── export.php             CSV + printable-PDF export
│
├── admin/                 Admin panel (role: admin)
│   ├── login.php
│   ├── dashboard.php      KPIs + attempts chart + recent activity
│   ├── quizzes.php        Quiz CRUD
│   ├── questions.php      Question CRUD with options
│   ├── attempts.php       View / filter / CSV-export all attempts
│   ├── certificates.php   Search, inspect, download, and revoke certificates
│   ├── users.php          Manage students (toggle, delete)
│   ├── feedback.php       Read & manage feedback
│   └── analytics.php      System-wide analytics
│
├── includes/              Shared PHP — db, auth, header, footer, helpers
│   ├── certificates.php   Eligibility, issuance, and verification helpers
│   └── certificate-template.php  Shared print/PDF certificate layout
├── config/                Application config
├── database/
│   ├── schema.sql         Full schema + sample content
│   └── migrations/        Safe incremental database updates
├── tests/
│   └── certificate-rules.php  Certificate tier and record validation checks
├── assets/
│   ├── css/style.css      Custom theme
│   ├── js/app.js          Timer, theme toggle, helpers
│   └── img/
└── uploads/               (reserved)
```

---

## 🗃️ Database schema

8 normalised tables with proper foreign keys, indexes, and `utf8mb4` charset:

| Table              | Purpose                                       |
| ------------------ | --------------------------------------------- |
| `users`            | Students + admins (role-based)                 |
| `quizzes`          | Quiz metadata (title, duration, passing %)     |
| `questions`        | Questions belonging to a quiz                  |
| `options`          | Choices for each question (one correct)        |
| `attempts`         | One row per submitted exam attempt             |
| `attempt_answers`  | Per-question record of what the student picked |
| `feedback`         | User feedback / bug reports                    |
| `certificates`     | Issued/revoked achievement certificates        |

Full DDL lives in [`database/schema.sql`](database/schema.sql).

### Upgrade an existing database

Back up the database, then import [`database/migrations/20261030_create_certificates.sql`](database/migrations/20261030_create_certificates.sql) in phpMyAdmin with `online_exam_system` selected. The migration only creates the certificates table if it does not exist and does not delete or rewrite existing rows. Do **not** rerun `install.php` against an existing database: the fresh-install schema drops and recreates the application's existing tables. Run `composer install` in the project directory so PDF downloads are available.

Certificate tiers are assigned from the exact stored score divided by maximum marks: below 35% earns none, 35%–<50% Silver, 50%–<80% Gold, and 80%–100% Platinum. Quiz pass/fail continues to use each quiz's configured `passing_percent`, independently of certificate tier.

Opening **My Certificates** also performs an idempotent backfill for that student from completed attempts already stored in the database. Existing eligible attempts are issued once; the unique attempt constraint prevents duplicate certificates.

---

## 🛡️ Security model

This project deliberately demonstrates a **modern, secure baseline** for PHP web apps:

- **Passwords** stored with `password_hash(PASSWORD_BCRYPT)` and verified with `password_verify`.
- **All SQL** uses **PDO prepared statements** — zero string interpolation into queries.
- **CSRF tokens** on every state-changing form (and verified server-side).
- **Session security**: HttpOnly + SameSite=Lax cookies, regeneration on login, distinct session name.
- **Output escaping**: every dynamic value goes through `htmlspecialchars(..., ENT_QUOTES)` via the `e()` helper.
- **Strict SQL mode** enabled at connection time.
- **Time-taken** is computed server-side (session-tracked) — students can't fake their timer client-side.

---

## 🧪 Default credentials

After running the installer, you can sign in with whatever you set up. If you accepted the defaults during install:

| Role    | Email               | Password      |
| ------- | ------------------- | ------------- |
| Admin   | _whatever you chose_ | _whatever you chose_ |
| Student | `student@oes.local` | `student@123` |

> ⚠️ **Change these in any non-local environment.**

## 🏅 Certificate workflow checks

Run the isolated rule and record-validation checks from the project directory:

```powershell
C:\xampp\php\php.exe tests\certificate-rules.php
```

1. Sign in as a student and open **My Certificates**. Eligible existing attempts are backfilled automatically; no retake is required. New completed exams issue a certificate in the same database transaction as the saved answers. Scores below 35% earn none.
2. Use a published test quiz with 100 one-mark questions to produce exact percentages of 25, 35, 49, 50, 79, 80, and 100. Submit the corresponding number of correct answers and check **My Certificates**:

   | Score | Expected result |
   | ----- | --------------- |
   | 25%   | No certificate  |
   | 35%   | Silver          |
   | 49%   | Silver          |
   | 50%   | Gold            |
   | 79%   | Gold            |
   | 80%   | Platinum        |
   | 100%  | Platinum        |

3. Open a certificate preview, download its PDF, and verify its ID using the public link. Check that the output is A4 landscape and that the type-specific border and badge match.
4. Sign in as admin, open **Certificates**, search by student/certificate ID, filter by tier, and revoke a test certificate with a reason. Its public verification page should report it invalid; the student view should display its revoked state and not offer a PDF download.

---

## 🛠️ Tech stack

- **Backend**: PHP 8.0+, PDO
- **Database**: MySQL 5.7+ / MariaDB 10.3+
- **Frontend**: Bootstrap 5.3, Chart.js 4, vanilla JS
- **No build tools required** — Bootstrap and Chart.js are loaded from a CDN

---

## 🧩 Extending it

Some ideas to take this further as a college project:

- 📧 Email-based password reset (PHPMailer + SMTP)
- 🎲 Randomised question order per attempt
- 🖼️ Image-based questions (upload to `uploads/`)
- 🧮 Negative marking / weighted scoring
- 🌐 i18n with `gettext`
- 📤 Bulk import questions from CSV
- 🤝 Per-course / per-department quiz tagging
- 📡 REST API on top of the same DB for a future React/Vue frontend

PRs welcome — see [Contributing](#contributing) below.

---

## ❓ Troubleshooting

**"Database connection failed"**
→ Check `config/config.php` host/user/password match your MySQL setup. Default XAMPP has user `root` with no password.

**"Database unavailable" in production**
→ Set `APP_DEBUG = false` in config to hide the error details (and check `error_log`).

**"Invalid or expired CSRF token"**
→ Your session likely expired. Reload the form. If it persists, check that `session.save_path` is writable.

**Want to reset everything?**
→ In phpMyAdmin: drop the `online_exam_system` database, then re-run `install.php`.

---

## 🤝 Contributing

This codebase is intentionally readable and minimal — perfect for college students who want to learn PHP fundamentals and contribute back.

1. Fork the repo
2. Create a feature branch (`git checkout -b feat/awesome-thing`)
3. Commit (`git commit -m 'feat: add awesome thing'`)
4. Push (`git push origin feat/awesome-thing`)
5. Open a Pull Request

Issues, bug reports, and feature requests are all welcome.

---

## 📜 License

[MIT](LICENSE) — use it freely in your projects (yes, including your college submission). Attribution appreciated but not required.

---

## 💬 Credits

Built with ❤️ for students who deserve a clean starting point — not a 2015-era PHP tutorial.

If this helped your project, consider giving the repo a ⭐ on GitHub!

---

## 🌐 Connect with the developer

[![LinkedIn](https://img.shields.io/badge/LinkedIn-Aashana-0A66C2?logo=linkedin&logoColor=white&style=for-the-badge)](https://in.linkedin.com/in/aashana1012)
[![GitHub](https://img.shields.io/badge/GitHub-aashishbharti04-181717?logo=github&logoColor=white&style=for-the-badge)](https://github.com/aashishbharti04)
[![YouTube](https://img.shields.io/badge/YouTube-@CodeWithAsur-FF0000?logo=youtube&logoColor=white&style=for-the-badge)](https://www.youtube.com/@CodeWithAsur)
[![Instagram](https://img.shields.io/badge/Instagram-@asurwave1012-E4405F?logo=instagram&logoColor=white&style=for-the-badge)](https://www.instagram.com/asurwave1012)
[![Gmail](https://img.shields.io/badge/Email-aashish@marketdoctorsonline.com-EA4335?logo=gmail&logoColor=white&style=for-the-badge)](mailto:aashish@marketdoctorsonline.com)

📺 **Subscribe to [@CodeWithAsur](https://www.youtube.com/@CodeWithAsur)** for more PHP / full-stack web-dev tutorials and project walkthroughs!

---

<div align="center">

**© 2026 Online Exam System · Crafted by [Aashish Bharti](https://github.com/aashishbharti04)** &middot; Licensed under [MIT](LICENSE)

*All rights reserved.*

</div>
