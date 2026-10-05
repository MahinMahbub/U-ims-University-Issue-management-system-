# U-IMS: University Issue Management System

![PHP](https://img.shields.io/badge/PHP-8.x-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL%20%2F%20MariaDB-4479A1?logo=mysql&logoColor=white)

A web app where students report campus problems, authorities verify and manage them, and everyone can follow progress in public. Built for United International University with plain PHP, MySQL and vanilla JavaScript. There is no framework and no build step.

A student reports a broken tap in the washroom with a photo. An authority verifies it, sets the priority and assigns a supervisor and an attendant. The student watches the status move from *Verified* to *In Progress* to *Resolved*, and other students can back the report with a reaction.

## Features

**Students**
- Register and log in with a Student ID, email and password
- Report an issue with a category, location, description, priority and optional evidence (JPG, PNG, WEBP or PDF, up to 5 MB)
- Dashboard with counts per status; edit your own reports until they are verified, after which they lock
- Issue page with a progress tracker, a timeline of authority updates and, when staff have been assigned, who is fixing it

**Everyone**
- Public **Verified Issues** feed with search and filters (category, status, priority)
- Monthly chart of issues submitted versus resolved
- One reaction per issue (Support, Same here or Urgent) and comments up to 500 characters
- Rejected issues stay visible with the reason, so decisions are transparent
- Light and dark theme, responsive layout with a mobile menu

**Authorities**
- Review queue with status filters and search by title, reporter or issue number
- Per-issue page to change status and priority, route to a department and message the reporter
- **Assign supervisors and attendants** to a job (see below)

**Administrators** (everything above, plus the admin panel)
- **Categories, departments and locations**: add, rename and remove. Anything still in use can't be removed, so existing issues never lose their data. Rename it instead.
- **Staff**: add supervisors and attendants (name, role, optional phone), edit them, deactivate them or remove them. Staff with job history can only be deactivated.
- Overview with totals, issues by category, issues that still need someone assigned, and open jobs

### Assigning staff to a job

1. An administrator adds people under **Admin → Staff**, for example *Mr. A, Supervisor*.
2. An authority or administrator opens **Manage issues**, picks the issue (say, the washroom repair) and chooses a person under **Assigned staff**. The dropdown shows each person's current workload.
3. A job can have several people. Logged-in users see "Being fixed by…" on the issue page; anonymous visitors don't see staff names.

Staff are records only. They don't log in to the system.

## Tech stack

- PHP 8.x (developed and tested on 8.3) with PDO, using prepared statements throughout
- MySQL 5.7+ or MariaDB 10.x (tested on MariaDB 10.11)
- Vanilla JavaScript and a single hand-written CSS file
- Apache, for example via XAMPP

## Getting started

1. **Install XAMPP** (or any Apache, PHP and MySQL stack) and start Apache and MySQL.
2. **Copy the project** into your web root as a folder named exactly `uims`, for example `C:\xampp\htdocs\uims`. The app uses absolute `/uims/...` links, so the folder name matters.
3. **Create the database.** In phpMyAdmin, open the **Import** tab and import `database.sql`. Or from a terminal:
   ```bash
   mysql -u root < database.sql
   ```
   This creates a database called `uims` with all tables and starter data.
4. **Check the connection** in `config/database.php`:
   ```php
   $host = "127.0.0.1";
   $db   = "uims";
   $user = "root";
   $pass = "";
   $port = 4306;   // XAMPP's default is 3306. Use whichever your MySQL runs on.
   ```
5. **Open** <http://localhost/uims/>.

On Linux or macOS, make sure the web server can write to `uploads/`.

### Demo accounts

| Role | Email | Password |
| --- | --- | --- |
| Student | `student@uims.local` | `password` |
| Authority | `authority@uims.local` | `password` |
| Administrator | `admin@uims.local` | `password` |

Delete these accounts or change their passwords before you put the app anywhere public.

### Upgrading an existing database

Copy the new files over the old ones, then optionally run `migration_staff_assignments.sql` in phpMyAdmin. The staff tables are also created automatically the first time a page needs them, so the migration is a convenience. It also repairs demo accounts that were created from an older `database.sql` whose password hash didn't match `password`. It only touches rows that still carry that old hash.

`migration_reactions_comments.sql` does the same for the reactions and comments tables on databases that predate the feed.

## Project structure

```
uims/
├── index.php               Home page and public statistics
├── register.php            Student registration
├── login.php, logout.php
├── dashboard.php           Student dashboard
├── submit_issue.php        Report an issue
├── edit_issue.php          Edit your own issue (until verified)
├── issues.php              Public Verified Issues feed
├── issue.php               Single issue: tracker, updates, assigned staff
├── issue_social.php        JSON endpoint for reactions and comments
├── authority.php           Review queue
├── manage_issue.php        Status, priority, department and staff assignment
├── admin.php               Admin panel: lists and staff
├── config/
│   ├── database.php        Database connection
│   ├── auth.php            Sessions, roles, CSRF, flash messages
│   ├── social.php          Reactions and comments helpers
│   └── staff.php           Staff and assignment helpers
├── partials/               Shared header and footer
├── assets/                 style.css, app.js, logo
├── uploads/                Evidence files (script execution blocked by .htaccess)
├── database.sql            Full schema and starter data
└── migration_*.sql         Upgrade scripts for existing databases
```

The database has these tables: `roles`, `departments`, `users`, `categories`, `locations`, `issues`, `evidence`, `issue_updates`, `notifications`, `issue_reactions`, `issue_comments`, `staff` and `issue_assignments`.

## How an issue moves through the system

`Pending Verification` → `More Information` or `Under Review` → `Verified` or `Rejected` → `In Progress` → `Resolved` → `Closed`

- Pending issues are visible only to their reporter and to staff.
- Students can edit a report until it is verified.
- The public feed shows Verified, In Progress, Resolved, Closed and Rejected issues.
- Priorities are Low, Medium, High and Critical.

## Configuration

| What | Where |
| --- | --- |
| Database host, user, password, port | `config/database.php` |
| Minimum password length (default 6) | `PASSWORD_MIN_LENGTH` in `register.php` |
| Allowed evidence types and size limit | `submit_issue.php` |
| Reaction types and comment length | `SOCIAL_REACTIONS` and `COMMENT_MAX_LENGTH` in `config/social.php` |
| Categories, departments, locations, staff | Admin panel (no code changes needed) |

## Security notes

- Passwords are hashed with `password_hash()` (bcrypt), and the session ID is regenerated on login.
- All queries use PDO prepared statements, and output is HTML-escaped.
- Registration, the admin panel, issue management and the feed's reactions and comments are protected with CSRF tokens.
- Uploaded files get random names, and `uploads/.htaccess` stops PHP from running inside that folder (Apache 2.4).
- Before deploying: use a dedicated database user instead of `root` with no password, serve over HTTPS, and remove the demo accounts.
- `uploads/` holds real user evidence. Keep it out of version control, for example by adding `uploads/*` and `!uploads/.htaccess` to `.gitignore`.
