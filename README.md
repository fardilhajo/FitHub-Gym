# FitHub Gym

FitHub Gym is a PHP and SQLite gym management web application developed for the LTW course project. It supports members, trainers and administrators through role-based workflows for class bookings, trainer management, equipment availability, nutrition plans, progress tracking, reports, featured promotions and public API access.

## Tech stack

- PHP with PDO
- SQLite
- HTML, CSS and vanilla JavaScript
- AJAX endpoints for dynamic search, notifications and equipment updates
- Built-in PHP development server for local execution

## Main features

### Authentication and profiles

- User registration, login and logout.
- Editable user profiles with name, username, password and profile photo.
- Role-based access control for members, trainers and administrators.
- Passwords stored with `password_hash()`.

### Members

- Browse available fitness classes.
- Filter classes by type, trainer, day or text search.
- Enrol in classes and cancel enrolments.
- Join the waitlist when a class is full.
- View enrolled classes in a personal dashboard.
- Rate and review attended classes.
- View trainer profiles, specializations and class schedules.
- Check equipment availability and reserve equipment time slots.
- Subscribe to membership plans.
- Track workout logs, fitness goals and progress statistics.
- View nutrition plans assigned by trainers.
- Submit reports and feedback to the administration team.

### Trainers

- Public trainer profile with bio, specializations and certifications.
- Public class schedule with enrollment counts.
- Private class roster management.
- Attendance and engagement analytics.
- Nutrition plan creation and meal assignment for members.
- Personal training availability and booking support.

### Administrators

- Manage users, members and trainers.
- Manage the class catalog and trainer assignments.
- Manage equipment and equipment availability.
- Manage reports and respond to member feedback.
- Manage featured promotions.
- Promote users to administrator status.
- Oversee platform data through the admin dashboard.

### Extra functionality

- AJAX class search in the navbar.
- AJAX equipment availability refresh.
- Notification support.
- Featured promotions on the homepage.
- SQLite database script with seed data.
- CSRF protection on forms that change data.
- PDO prepared statements for database access.
- Public read-only REST API for third-party integrations.

## Requirements

Make sure the following are installed:

- PHP 8 or newer
- PHP SQLite/PDO SQLite extension
- SQLite CLI, optional but useful for manually resetting the database

No Composer dependencies are required.

## Running locally

From the project root, start the PHP development server:

```bash
php -S localhost:9000
```

Then open the application:

```text
http://localhost:9000/
```

The application automatically creates `database/database.db` from `database/database.sql` if the database file does not exist.

## Resetting the database

To recreate the database manually on Linux/macOS:

```bash
rm -f database/database.db
sqlite3 database/database.db < database/database.sql
php -S localhost:9000
```

To recreate the database manually on Windows PowerShell:

```powershell
Remove-Item database/database.db -ErrorAction SilentlyContinue
sqlite3 database/database.db ".read database/database.sql"
php -S localhost:9000
```

## Demo credentials

| Role | Username | Password |
| --- | --- | --- |
| Admin | `admin` | `p4s5w0rd` |
| Member | `member` | `123456` |
| Trainer | `ana` | `123456` |
| Trainer | `ricardo` | `123456` |
| Trainer | `sofia` | `123456` |
| Trainer | `david` | `123456` |
| Sample members | `dummy01` to `dummy30` | `123456` |

## Main pages

| Page | Description |
| --- | --- |
| `index.php` | Homepage/dashboard with featured content and role-aware navigation. |
| `login.php` | User login. |
| `register.php` | New member registration. |
| `edit-profile.php` | Profile editing, password changes and profile photo updates. |
| `book-class.php` | Class schedule, filters, enrolment, cancellation, waitlist and average ratings. |
| `my-classes.php` | Member's enrolled classes, attendance history, ratings and reviews. |
| `trainers.php` | Public trainer listing. |
| `trainer-profile.php?id=3` | Public trainer profile with classes and profile details. |
| `personal-training.php` | Personal training availability and booking flow. |
| `trainer-roster.php` | Trainer-only roster management for assigned classes. |
| `trainer-analytics.php` | Trainer-only attendance statistics, ratings and class engagement. |
| `trainer-nutrition.php` | Trainer-only nutrition plan and meal assignment management. |
| `equipment.php` | Equipment availability and reservation management with AJAX refresh. |
| `membership-plans.php` | Membership plan selection and subscription flow. |
| `progress.php` | Member workout logs, goals and progress tracking. |
| `nutrition.php` | Member view of assigned nutrition plans and meal breakdowns. |
| `reports.php` | Member report submission and admin responses. |
| `admin.php` | Admin dashboard for users, classes, equipment, reports and promotions. |
| `logout.php` | Ends the current session. |

## Public REST API

The project includes a public, read-only REST API for third-party applications. It returns JSON and does not require login.

Open the API documentation page:

```text
http://localhost:9000/api/public/
```

### Class schedules

```http
GET /api/public/classes.php
```

Examples:

```text
http://localhost:9000/api/public/classes.php
http://localhost:9000/api/public/classes.php?day=Monday
http://localhost:9000/api/public/classes.php?type=Yoga
http://localhost:9000/api/public/classes.php?trainer=3
http://localhost:9000/api/public/classes.php?q=strength
http://localhost:9000/api/public/classes.php?id=1
```

The response includes class details, schedule information, capacity, enrolled count, waitlist count, trainer information and average rating.

### Trainer profiles

```http
GET /api/public/trainers.php
```

Examples:

```text
http://localhost:9000/api/public/trainers.php
http://localhost:9000/api/public/trainers.php?id=3
http://localhost:9000/api/public/trainers.php?q=mobility
```

When no `id` is provided, the API returns a list of trainers. When an `id` is provided, it returns the selected trainer profile with public classes and availability.

### Equipment availability

```http
GET /api/public/equipment.php
```

Examples:

```text
http://localhost:9000/api/public/equipment.php
http://localhost:9000/api/public/equipment.php?category=Treadmills
http://localhost:9000/api/public/equipment.php?status=Available
http://localhost:9000/api/public/equipment.php?id=1
http://localhost:9000/api/public/equipment.php?id=1&start=2026-05-30T10:00&end=2026-05-30T11:00
```

The equipment endpoint can also check whether a specific equipment item is available during a requested time slot.

### Testing the API

In a browser, open any of the full URLs above.

In PowerShell:

```powershell
Invoke-RestMethod http://localhost:9000/api/public/equipment.php
```

With curl:

```bash
curl "http://localhost:9000/api/public/classes.php?day=Monday"
```

The public API only exposes public information. It does not expose private user data such as usernames, emails, password hashes or member account details.

## Project structure

```text
.
├── api/                 # JSON endpoints used by AJAX and public API routes
├── css/                 # Global, layout, component and page-specific styles
├── database/            # SQLite database file and SQL seed script
├── docs/                # UML model and navigation diagram
├── includes/            # Shared PHP helpers, database bootstrap and security functions
├── js/                  # JavaScript for admin views, navbar and interactive forms
├── uploads/             # Uploaded user assets
└── *.php                # Main application pages
```

## Database

The main database schema and seed data are stored in:

```text
database/database.sql
```

The runtime database is:

```text
database/database.db
```

The application database connection is handled by:

```text
includes/db.php
```

This file enables foreign keys, initializes the database when needed and loads feature-specific schema checks from `includes/feature-schema.php`.

## Security notes

- Passwords are hashed with PHP's `password_hash()`.
- Database operations use PDO and prepared statements.
- Mutating forms use CSRF tokens.
- Pages check the user's session role before allowing protected actions.
- Public API endpoints are read-only and avoid exposing private account fields.

## Documentation

Additional project documentation is available in the `docs/` folder:

- `docs/UML Model.png`
- `docs/navigation-diagram.svg`

## Delivery status

The project is ready for final delivery with implemented member, trainer, administrator, booking, equipment, nutrition, progress, reports, promotion and public API features. The repository includes seeded data and demo accounts so the application can be tested immediately after starting the PHP server.
