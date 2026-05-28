# ltw02g06

A PHP/SQLite gym management platform for FitHub Gym.

## Features

**All users:**
- [x] Register a new account.
- [x] Log in and out.
- [x] Edit their profile, including name, username, password, and profile photo.

**Members:**
- [x] Browse the schedule of available fitness classes, filtering by type, trainer, day, or text search.
- [x] Enroll in and cancel enrollment from upcoming classes, subject to capacity limits.
- [x] View trainer profiles, including their specializations and the classes they teach.
- [x] Check the current availability of equipment in the main training area.
- [x] Leave ratings and reviews for classes they have attended.

**Trainers:**
- [x] Public profile with bio, specializations, and certifications.
- [x] Public class schedule with enrollment counts.
- [x] Private roster management page.

**Admins:**
- [x] Manage members and trainers.
- [x] Manage the class catalog and assign trainers.
- [x] Manage equipment availability.
- [x] Elevate a user to admin status.
- [x] Oversee platform operation.

**Extra:**
- [x] Ajax class search in the navbar.
- [x] Ajax equipment availability refresh.
- [x] SQLite database script and seed data.
- [x] CSRF protection on forms that change data.
- [x] Passwords stored with `password_hash()`.
- [x] Membership Plans.
- [x] PDO prepared statements.
- [x] Member Progress Tracking
- [x] Nutrition Plans
- [x] Disputes and Feedback
- [x] Featured Promotions

## Running

From the project root:

```bash
php -S localhost:9000
```

Then open:

```text
http://localhost:9000/
```

The app automatically creates `database/database.db` from `database/database.sql` if the database file does not exist. You can also recreate it manually:

```bash
rm -f database/database.db
sqlite3 database/database.db < database/database.sql
php -S localhost:9000
```

## Credentials

- admin / p4s5w0rd
- member / 123456
- ana / 123456
- ricardo / 123456
- sofia / 123456
- david / 123456
- dummy01 through dummy30 / 123456

## Pages

- `index.php` - dashboard/homepage
- `book-class.php` - class schedule, filters, enroll/cancel booking with average ratings
- `my-classes.php` - member's enrolled classes with rating and review submission
- `trainers.php` - trainer list
- `trainer-profile.php?id=3` - trainer profile and class schedule
- `trainer-analytics.php` - trainer attendance stats, ratings and class engagement (trainer only)
- `trainer-nutrition.php` - create and assign nutrition plans to members (trainer only)
- `equipment.php` - equipment availability with Ajax refresh
- `progress.php` - workout log, fitness goals and progress tracking (member only)
- `nutrition.php` - view assigned nutrition plans with meal and calorie breakdown (member only)
- `reports.php` - submit reports and view responses (member), admin manages via Reports tab in admin dashboard
- `login.php`, `register.php`, `edit-profile.php` - account flows

## Notes for final delivery

The final delivery still needs the admin management screens and member reviews if the group wants every base requirement checked. The current version covers the expected week 10-12 work: database, PHP pages, class booking, dynamic trainers/equipment, and JavaScript/Ajax.
