# Online Exam System

A PHP and MySQL application for managing online exams, student results, and achievement certificates. It includes student and administrator areas and is designed to run locally with XAMPP.

## Features

- Student registration, login, profile management, and role-based administrator access.
- Published course catalog with search, category and difficulty filters, sorting, and pagination.
- Twenty-five seeded courses with multiple-choice questions and local course photographs.
- Timed exams, server-side grading, saved answers, result history, and downloadable reports.
- Student leaderboard ranked by total score, with completed attempts as a tie-breaker.
- Automatic Silver, Gold, and Platinum certificates based on the submitted exam percentage, with PDF downloads and public verification.
- Administrator tools for managing courses, questions, students, exam attempts, certificates, and feedback.
- Responsive interface, dark/light theme, and dashboard charts.

Certificate eligibility is based on actual score divided by maximum marks:

| Percentage | Certificate |
| ---------- | ----------- |
| Below 35%  | None        |
| 35%–49.99% | Silver      |
| 50%–79.99% | Gold        |
| 80%–100%   | Platinum    |

Quiz pass/fail status uses the quiz's configured passing percentage and is separate from certificate eligibility.

## Requirements

- PHP 8.0 or later with PDO MySQL enabled.
- MySQL 5.7 or later, or MariaDB 10.3 or later.
- Apache and MySQL, such as those provided by XAMPP.
- Composer, to install Dompdf for certificate PDF downloads.
- An internet connection for Bootstrap, Bootstrap Icons, and Chart.js assets loaded from CDNs.

## Fresh installation with XAMPP

1. Copy the project into the web root, for example `C:\xampp\htdocs\online-exam-system`.
2. Check the database settings in [`config/config.php`](config/config.php). XAMPP defaults are host `127.0.0.1`, port `3306`, database `online_exam_system`, user `root`, and an empty password. For local overrides, create `config/config.local.php`; this file is ignored by Git.
3. Install PHP dependencies from the project directory:

   ```powershell
   composer install
   ```

4. Start Apache and MySQL in the XAMPP Control Panel, then open:

   ```text
   http://localhost/online-exam-system/install.php
   ```

5. Follow the installer to create an administrator account. The installer loads the schema and course catalog (25 courses total, including 215 questions), and can optionally create a demo student account.
6. Remove `install.php` from the web server after installation. **Do not run the installer against an existing database:** `database/schema.sql` drops and recreates application tables.
7. To assign the bundled course photographs, run the thumbnail migration from PowerShell:

   ```powershell
   C:\xampp\php\php.exe C:\xampp\htdocs\online-exam-system\database\migrations\20261030_add_course_thumbnails.php
   ```

Student sign-in: <http://localhost/online-exam-system/login.php>  
Administrator sign-in: <http://localhost/online-exam-system/admin/login.php>

The optional demo student created by the installer uses `student@oes.local` / `student@123`. Change or remove this account outside a local development environment.

## Updating an existing installation

Back up the database before applying migrations. Do not run `install.php` against an existing installation.

### Course catalog

The course catalog migration is additive and idempotent. It adds missing difficulty fields and inserts the 20 additional courses and their questions without deleting the original five courses or existing attempts:

```powershell
C:\xampp\php\php.exe C:\xampp\htdocs\online-exam-system\database\migrations\20261030_expand_course_catalog.php
```

### Course photographs

Run the thumbnail migration after the 25 courses are present. It maps existing courses by verified ID or unique exact title and assigns local photographs without changing questions:

```powershell
C:\xampp\php\php.exe C:\xampp\htdocs\online-exam-system\database\migrations\20261030_add_course_thumbnails.php
```

Course photographs are stored in [`assets/images/courses/`](assets/images/courses/). Their Unsplash source IDs and license information are documented in [`ATTRIBUTION.txt`](assets/images/courses/ATTRIBUTION.txt). The administrator quiz form accepts optional JPEG, PNG, or WebP thumbnails up to 5 MB.

### Certificates

The current schema includes the certificates table. To add it to an older database that does not have it, import [`database/migrations/20261030_create_certificates.sql`](database/migrations/20261030_create_certificates.sql) using phpMyAdmin with `online_exam_system` selected. The migration creates the table if it is missing.

Eligible completed attempts are issued certificates automatically. Opening **My Certificates** also performs an idempotent backfill for eligible historical attempts. A unique attempt constraint prevents duplicate certificates.

## Project structure

```text
online-exam-system/
├── admin/                 Administrator pages
├── assets/
│   ├── css/style.css      Shared styles
│   ├── images/courses/    Local course thumbnails and attribution
│   ├── img/               Other application images
│   └── js/app.js          Shared browser behavior
├── config/
│   └── config.php         Database and application configuration
├── database/
│   ├── schema.sql         Fresh-install schema and original sample courses
│   └── migrations/        Incremental catalog, thumbnail, and certificate updates
├── includes/              Database, authentication, layout, and certificate helpers
├── tests/                 PHP regression and data validation scripts
├── uploads/               Admin-uploaded course thumbnails
├── index.php              Public landing page
├── install.php            One-time fresh-install setup
├── exams.php              Student course catalog
├── take-exam.php          Timed exam
├── submit-exam.php        Submission and server-side grading
├── result.php             Exam result
├── history.php            Student attempt history
├── leaderboard.php        Student leaderboard
├── certificates.php       Student certificate library
├── certificate.php        Certificate preview
├── certificate-pdf.php    Certificate PDF download
└── verify-certificate.php Public certificate verification
```

## Database

The application schema uses InnoDB, foreign keys, and `utf8mb4`. Core tables:

| Table | Purpose |
| ----- | ------- |
| `users` | Student and administrator accounts |
| `quizzes` | Course and exam settings, publication status, and image path |
| `questions` | Exam questions and marks |
| `options` | Multiple-choice options and correct-answer flags |
| `attempts` | Submitted scores, maximum marks, and attempt metadata |
| `attempt_answers` | Saved answer selections and correctness |
| `feedback` | Student feedback |
| `certificates` | Issued and revoked certificate records |

See [`database/schema.sql`](database/schema.sql) for the full schema.

## Checks

Run these commands from the project directory. The catalog and thumbnail checks use the configured database and require the seeded 25-course installation. The certificate rules check validates certificate percentage boundaries and record consistency.

```powershell
C:\xampp\php\php.exe tests\certificate-rules.php
C:\xampp\php\php.exe tests\course-catalog.php
C:\xampp\php\php.exe tests\course-thumbnails.php
```

## Security and deployment notes

- Passwords are stored as hashes; never store plain-text passwords.
- Database access uses PDO, and user-provided values are validated and escaped before output.
- State-changing forms use CSRF protection.
- Keep environment-specific database credentials in the ignored `config/config.local.php`.
- Set `APP_DEBUG` to `false` for deployment and protect the installer by removing `install.php` after setup.
- Configure HTTPS and secure web-server settings before exposing the application publicly.

## Troubleshooting

- **Database connection error:** Confirm MySQL is running and check the values in `config/config.php` or `config/config.local.php`.
- **Certificate PDF unavailable:** Run `composer install` and verify that the `vendor/` directory was created.
- **Course thumbnails missing:** Confirm `assets/images/courses/` was deployed and run the thumbnail migration.
- **CSRF token error:** Reload the page and submit again; check that PHP sessions can be written by the web server.

