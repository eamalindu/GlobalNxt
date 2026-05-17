# GlobalNxt

![Private Repo](https://img.shields.io/badge/Repository-Private-red)
![Version](https://img.shields.io/badge/version-1.0-blue)
![Backend](https://img.shields.io/badge/backend-SpringBoot-success)
![Frontend](https://img.shields.io/badge/frontend-HTML%2FCSS%2FJS-yellow)
![Database](https://img.shields.io/badge/database-MySQL-orange)

GlobalNxt is a PHP-based document review platform for academic workflows.  
It supports role-based access for **staff** and **agents**, where staff upload student PDFs and agents review, approve, or reject submissions.

## Features

- **Role-based authentication**
  - Login flow with active-user validation
  - Session-based access control for staff and agent dashboards

- **Staff workflow**
  - Upload student documents (PDF only)
  - Enter student/profile metadata (name, ID, programme, document type, notes)
  - Track personal submission status (pending, approved, rejected)
  - View last 10 submitted records on dashboard

- **Agent workflow**
  - View global submission statistics
  - Review latest uploaded documents
  - Preview PDFs in browser
  - Download documents securely
  - Approve or reject with remarks (remarks required for rejection)

- **Document integrity & validation**
  - PDF MIME validation
  - 5MB size limit
  - Unique file naming and organized upload folders (`uploads/YYYY/MM/`)
  - SHA-256 hash generation stored per file

## Tech Stack

- PHP (session-based web app)
- MySQL (via PDO)
- Bootstrap + custom CSS
- Dotenv configuration loading

## Project Structure

```text
GlobalNxt/
├── agent/            # Agent dashboard and review screens
├── staff/            # Staff dashboard and upload screen
├── config/           # App/session constants and DB connection
├── includes/         # Shared header/footer includes
├── css/              # Bootstrap and page-level styles
├── js/               # Frontend scripts
├── uploads/          # Uploaded files storage
├── index.php         # Login page
├── login.php         # Login handler
├── logout.php        # Logout handler
└── serve_pdf.php     # Secure PDF streaming endpoint
```

## Setup

### 1) Prerequisites

- PHP 8.x+
- MySQL / MariaDB
- Web server (Apache/Nginx) or local PHP server

### 2) Configure environment

Create a `.env` file in the project root:

```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=globalnxt
DB_USER=root
DB_PASS=your_password
```

> The app reads database variables from `.env` via `config/db.php`.

### 3) Ensure required folders exist

- `uploads/` must be writable by the web server process.

### 4) Database expectations

The current code expects at least:

- `users` table with fields used in auth and roles (`id`, `username`, `password`, `type`, `status`)
- `documents` table with fields used in upload/review flow (`id`, `uploaded_by`, `student_name`, `student_id`, `programme_name`, `document_type`, `document_name`, `file_path`, `file_hash`, `notes`, `status`, `remarks`, `reviewed_by`, `reviewed_at`, `created_at`)

### 5) Run the application

Serve the project root and open:

- `http://localhost/.../index.php`

## Status Values

- `pending`
- `under_review`
- `approved`
- `rejected`

## Security & Validation Highlights

- Passwords are verified using `password_verify()`.
- SQL queries are parameterized with prepared statements.
- Output is escaped using `htmlspecialchars()` in UI rendering.
- PDF access requires a valid authenticated session (`serve_pdf.php`).

## Known Limitations / Future Enhancements

- Session timeout constant is defined in `config/app.php` (`SESSION_TIMEOUT = 1800`), but active timeout enforcement logic is not yet implemented.
