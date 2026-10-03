# U-IMS — University Issue Management System

U-IMS (University Issue Management System) is a PHP and MySQL-based web application for reporting, verifying, managing, and tracking university facility and service-related issues.

The system provides separate workflows for **Students**, **Authorities**, and **Administrators**, allowing an issue to move from an initial student report through verification, progress updates, and eventual resolution.

---

## Table of Contents

- [Overview](#overview)
- [Key Features](#key-features)
- [User Roles](#user-roles)
- [Issue Lifecycle](#issue-lifecycle)
- [Technology Stack](#technology-stack)
- [Project Structure](#project-structure)
- [Database Design](#database-design)
- [Requirements](#requirements)
- [Installation and Setup](#installation-and-setup)
- [Configuration](#configuration)
- [Running the Project](#running-the-project)
- [Demo Accounts](#demo-accounts)
- [Application Workflow](#application-workflow)
- [Security Features](#security-features)
- [File Uploads](#file-uploads)
- [Troubleshooting](#troubleshooting)
- [Future Improvements](#future-improvements)
- [License and Attribution](#license-and-attribution)

---

## Overview

U-IMS provides a centralized platform where students can report university problems such as:

- Infrastructure problems
- Academic issues
- IT and technology problems
- Sanitation issues
- Security concerns
- Other university-related problems

Students can provide a title, description, category, location, observed date/time, priority, and optional supporting evidence.

Authorities can review submitted issues, change their status, assign departments, update priorities, add progress messages, and mark issues as resolved or closed.

Administrators have additional management capabilities for maintaining categories, departments, and locations and viewing system-level issue statistics.

---

## Key Features

### Student Features

- Student registration and login
- Secure password hashing
- Submit a new issue
- Select issue category and location
- Set a suggested priority
- Add observed date/time
- Upload supporting evidence
- Edit previously submitted issues
- Delete submitted issues
- View personal issue history
- Track issue status
- View authority resolution updates
- Receive issue-status notifications
- Browse publicly verified issues

### Authority Features

- Role-protected authority dashboard
- View all submitted issues
- Review pending reports
- Verify or reject reports
- Request more information
- Move issues through different workflow states
- Change issue priority
- Assign issues to departments
- Add progress/update messages
- Notify the issue reporter after status changes
- Delete issues when necessary

### Administrator Features

- Administrator-only panel
- View total issue statistics
- View resolved, unresolved, and critical issue counts
- Add categories
- Add departments
- Add locations
- Remove unused categories
- View issue counts by category

### UI Features

- Responsive layout
- Light/dark theme
- SVG-based theme toggle
- Persistent theme preference using `localStorage`
- Animated hero text
- Confirmation prompts for destructive actions
- Reusable header and footer
- Status and priority badges
- Search and filtering for verified issues

---

## User Roles

| Role | Main Responsibilities |
|---|---|
| **Student** | Report and track university issues |
| **Authority** | Verify, update, assign, and resolve issues |
| **Administrator** | Manage system configuration and view system statistics |

Access to protected pages is controlled through PHP sessions and role-based authorization.

---

## Issue Lifecycle

An issue can move through the following statuses:

```text
Pending Verification
        |
        v
More Information
        |
        v
Under Review
        |
        v
Verified
        |
        v
In Progress
        |
        v
Resolved
        |
        v
Closed
```

An issue may also be marked:

```text
Rejected
```

The authority can add a message whenever an update is made. These updates are stored in the `issue_updates` table and displayed on the issue details page.

---

## Technology Stack

### Frontend

- HTML5
- CSS3
- JavaScript
- SVG
- Responsive UI

### Backend

- PHP
- PDO
- PHP Sessions

### Database

- MySQL
- UTF-8 / `utf8mb4`

### Development Environment

The project is designed to run conveniently with:

- XAMPP
- Apache
- MySQL

Other PHP/MySQL local development environments can also be used with appropriate configuration changes.

---

## Project Structure

```text
uims/
│
├── admin.php
├── authority.php
├── dashboard.php
├── edit_issue.php
├── index.php
├── issue.php
├── issues.php
├── login.php
├── logout.php
├── register.php
├── submit_issue.php
│
├── database.sql
│
├── config/
│   ├── auth.php
│   └── database.php
│
├── partials/
│   ├── footer.php
│   └── header.php
│
├── assets/
│   ├── app.js
│   ├── style.css
│   └── United_International_University_Monogram.svg
│
└── uploads/
    ├── .htaccess
    └── evidence files
```

### Important Files

| File | Purpose |
|---|---|
| `index.php` | Landing/home page |
| `register.php` | Student registration |
| `login.php` | User authentication |
| `logout.php` | Session logout |
| `dashboard.php` | Student dashboard |
| `submit_issue.php` | Issue submission |
| `edit_issue.php` | Edit an existing student issue |
| `issue.php` | Issue details and resolution updates |
| `issues.php` | Public verified issue listing and filtering |
| `authority.php` | Authority issue-management dashboard |
| `admin.php` | Administrator management panel |
| `config/database.php` | MySQL/PDO connection |
| `config/auth.php` | Authentication and authorization helpers |
| `database.sql` | Database schema and sample data |
| `assets/style.css` | Application styling |
| `assets/app.js` | Theme, confirmation, file-name, and hero animations |
| `partials/header.php` | Shared navigation/header |
| `partials/footer.php` | Shared footer/scripts |

---

## Database Design

The application uses the following main tables:

```text
roles
  |
  +---- users
          |
          +---- issues
          |       |
          |       +---- evidence
          |       |
          |       +---- issue_updates
          |       |
          |       +---- notifications
          |
          +---- departments

categories ---- issues
locations  ---- issues
departments ---- issues
```

### Main Tables

#### `roles`

Stores the available system roles:

- Student
- Authority
- Administrator

#### `users`

Stores user account information including:

- Name
- Email
- Student ID
- Department
- Batch
- Password hash
- Role

#### `issues`

Stores the main issue/report data:

- Reporter
- Category
- Location
- Department
- Title
- Description
- Observed time
- Priority
- Status
- Rejection reason
- Creation/update timestamps

#### `evidence`

Stores supporting files uploaded for issues.

#### `issue_updates`

Stores authority-generated progress messages and status updates.

#### `notifications`

Stores notifications sent to issue reporters.

#### `categories`

Stores issue categories.

#### `locations`

Stores university locations.

#### `departments`

Stores departments that can be assigned to issues.

---

## Requirements

Before installing the application, make sure the system has:

- PHP with PDO MySQL support
- MySQL/MariaDB
- Apache or another PHP-compatible web server
- A modern web browser
- Permission for PHP to write to the `uploads/` directory

For a typical XAMPP setup, Apache and MySQL should be running from the XAMPP Control Panel.

---

## Installation and Setup

### 1. Install XAMPP

Install XAMPP or another PHP/MySQL development environment.

Start:

```text
Apache
MySQL
```

### 2. Copy the Project

Place the `uims` project folder inside the web server document root.

For XAMPP on Windows:

```text
C:\xampp\htdocs\uims
```

The application should then be accessible from:

```text
http://localhost/uims/
```

### 3. Create the Database

Open phpMyAdmin:

```text
http://localhost/phpmyadmin/
```

Import:

```text
uims/database.sql
```

The SQL file automatically creates the `uims` database and its tables.

### 4. Configure Database Connection

Open:

```text
config/database.php
```

The default configuration is:

```php
$host = "127.0.0.1";
$db = "uims";
$user = "root";
$pass = "";
$port = 4306;
```

Change these values if your MySQL installation uses different credentials or a different port.

For a standard XAMPP installation, MySQL commonly uses port `3306`. If your MySQL server uses `3306`, update:

```php
$port = 3306;
```

### 5. Check Upload Permissions

The application stores uploaded evidence inside:

```text
uims/uploads/
```

Make sure PHP/Apache can write to this directory.

The included `.htaccess` file prevents PHP-like files from being executed from the upload directory.

---

## Configuration

### Database

Database configuration is located at:

```text
config/database.php
```

### Authentication

Authentication and authorization helpers are located at:

```text
config/auth.php
```

Important functions include:

```php
require_login();
current_user();
require_role();
e();
```

### Application URL

Some navigation links currently use:

```text
/uims/
```

If the project is installed under a different directory, update those absolute paths in the PHP templates.

---

## Running the Project

After Apache and MySQL are running and the database has been imported:

1. Open a browser.
2. Go to:

```text
http://localhost/uims/
```

3. Register a student account or use one of the demo accounts.
4. Submit an issue as a student.
5. Log in as an authority to review and update the issue.
6. Log in as an administrator to manage categories and view statistics.

---

## Demo Accounts

The supplied SQL database contains demo accounts.

| Role | Email | Password |
|---|---|---|
| Student | `student@uims.local` | `password` |
| Authority | `authority@uims.local` | `password` |
| Administrator | `admin@uims.local` | `password` |

> **Security note:** These credentials are intended for local development/demo purposes. Change or remove them before deploying the application to a real environment.

---

## Application Workflow

### Student Workflow

```text
Register
   |
   v
Login
   |
   v
Student Dashboard
   |
   v
Report Issue
   |
   +--> Add category
   |
   +--> Add location
   |
   +--> Set priority
   |
   +--> Upload evidence
   |
   v
Pending Verification
```

Students can subsequently edit their own reports and monitor status updates.

### Authority Workflow

```text
Authority Login
      |
      v
Authority Dashboard
      |
      v
Review Issue
      |
      +--> Request More Information
      |
      +--> Reject
      |
      +--> Verify
               |
               v
          In Progress
               |
               v
           Resolved
               |
               v
             Closed
```

### Administrator Workflow

```text
Administrator Login
        |
        v
Administrator Panel
        |
        +--> View statistics
        +--> Add category
        +--> Add department
        +--> Add location
        +--> Remove unused category
```

---

## Search and Filtering

The verified issues page supports:

- Text search
- Category filtering
- Status filtering
- Priority filtering

Only issues with these public statuses are displayed:

```text
Verified
In Progress
Resolved
Closed
```

Pending verification issues are not publicly visible to other users.

---

## Security Features

The project includes several basic security measures:

### Password Hashing

Passwords are stored using PHP's password hashing API:

```php
password_hash($password, PASSWORD_DEFAULT);
```

Passwords are verified with:

```php
password_verify($password, $user['password_hash']);
```

### Prepared Statements

Database operations use PDO prepared statements to reduce SQL injection risks.

Example:

```php
$stmt = $pdo->prepare("SELECT * FROM users WHERE email=?");
$stmt->execute([$email]);
```

### Role-Based Access Control

Protected pages use role checks.

Example:

```php
require_role(['Authority', 'Administrator']);
```

### Output Escaping

User-controlled output is escaped with:

```php
htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
```

through the helper:

```php
e($value);
```

### Upload Restrictions

Evidence uploads are limited to:

```text
JPG
PNG
WEBP
PDF
```

with a maximum file size of:

```text
5 MB
```

The `uploads/.htaccess` file also blocks execution of PHP-like files.

---

## File Uploads

Evidence files are stored under:

```text
uploads/
```

Generated filenames use a unique prefix such as:

```text
evidence_<unique-id>.jpg
```

This prevents the original filename from directly becoming the stored server filename.

Allowed MIME types are:

```text
image/jpeg
image/png
image/webp
application/pdf
```

Maximum size:

```text
5 MB
```

---

## Frontend Behavior

The application JavaScript in `assets/app.js` provides:

### Dark Mode

The selected theme is stored in browser `localStorage`:

```text
uims-theme
```

The theme persists when the user navigates between pages.

### Delete Confirmation

Buttons with a `data-confirm` attribute display a browser confirmation dialog before destructive actions.

### Evidence Filename Display

When a user selects an evidence file, the selected filename is displayed below the upload field.

### Hero Text Animation

The home page cycles through:

```text
Track progress.
Build transparency.
Improve the university.
```

using a typewriter-style animation.

---

## Troubleshooting

### Database Connection Failed

Check:

1. MySQL is running.
2. The database `uims` exists.
3. Username/password are correct.
4. The configured port matches the MySQL server.

Check:

```text
config/database.php
```

### Page Shows 404

Make sure the project is located in the web server root, for example:

```text
C:\xampp\htdocs\uims
```

Then open:

```text
http://localhost/uims/
```

### Uploaded Evidence Does Not Work

Check:

- `uploads/` exists.
- Apache/PHP has write permission.
- The file is smaller than 5 MB.
- The file uses JPG, PNG, WEBP, or PDF.
- PHP upload settings allow files of the required size.

### Login Does Not Work

Verify that:

- The database was imported correctly.
- The demo account exists.
- The password is correct.
- The PHP session system is working.

### Dark Mode Does Not Persist

Make sure the browser allows `localStorage` and JavaScript is enabled.

---

## Future Improvements

Potential improvements for a production-ready version include:

- CSRF protection for POST forms
- Stronger server-side upload validation using `finfo`
- Rate limiting for login and issue submission
- Email notifications
- Password reset functionality
- User profile management
- Authority-specific department permissions
- Audit logs for administrative actions
- Pagination for large issue lists
- Advanced search
- Dashboard charts and analytics
- REST API
- AJAX-based status updates
- Better notification center with read/unread controls
- Image previews for uploaded evidence
- Multiple evidence files per report
- Automated database backups
- Environment-variable based configuration
- Production HTTPS configuration
- More granular administrator permissions

---

## Development Notes

This project uses server-rendered PHP pages rather than a separate frontend framework. Shared UI elements are kept in:

```text
partials/header.php
partials/footer.php
```

Database access is centralized through PDO in:

```text
config/database.php
```

Authentication and authorization helpers are centralized in:

```text
config/auth.php
```

This structure makes the project relatively easy to extend while keeping the application simple enough for an academic or university project.

---

## License and Attribution

U-IMS is an academic/project implementation of a University Issue Management System.

The interface includes the United International University monogram asset supplied with the project.

For real-world deployment, ensure that the use of university branding, logos, student information, uploaded evidence, and other institutional data complies with the institution's policies and applicable law.

---

## Quick Start

```text
1. Install XAMPP
2. Start Apache + MySQL
3. Copy uims/ to C:\xampp\htdocs\
4. Import database.sql in phpMyAdmin
5. Check config/database.php
6. Open http://localhost/uims/
7. Login with a demo account
8. Test Student → Authority → Administrator workflows
```

**U-IMS — Report problems. Track progress. Improve the university.**
